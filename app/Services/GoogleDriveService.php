<?php

namespace App\Services;

use Exception;
use Google_Client;
use Google_Service_Drive;
use Google_Service_Drive_DriveFile;
use Google_Service_Drive_Permission;
use Illuminate\Support\Facades\Log;
use Psr\Http\Message\ResponseInterface;

class GoogleDriveService
{
    private function getClient()
    {
        $client = new Google_Client;
        $client->setClientId(config('filesystems.disks.google.clientId'));
        $client->setClientSecret(config('filesystems.disks.google.clientSecret'));
        $client->refreshToken(config('filesystems.disks.google.refreshToken'));

        return new Google_Service_Drive($client);
    }

    /**
     * Sube un archivo a Google Drive y retorna su ID y URL pública.
     *
     * @return array{id: string, link: string}
     */
    public function subirArchivo(string $rutaAbsoluta, string $mime, string $nombreArchivo, string $idCarpetaDestino): array
    {
        $service = $this->getClient();

        $fileMetadata = new Google_Service_Drive_DriveFile([
            'name' => $nombreArchivo,
            'parents' => [$idCarpetaDestino],
        ]);

        $driveFile = $service->files->create($fileMetadata, [
            'data' => file_get_contents($rutaAbsoluta),
            'mimeType' => $mime,
            'uploadType' => 'multipart',
            'fields' => 'id, webViewLink',
        ]);

        return ['id' => $driveFile->id, 'link' => $driveFile->webViewLink];
    }

    /**
     * Sube un archivo a Google Drive y retorna la URL pública.
     */
    public function uploadToDrive($archivo, $nombreArchivo, $idCarpetaDestino)
    {
        $resultado = $this->subirArchivo(
            $archivo->getRealPath(),
            $archivo->getMimeType(),
            $nombreArchivo,
            $idCarpetaDestino
        );

        return $resultado['link'];
    }

    /**
     * Comparte un archivo con enlace público de solo lectura.
     * Idempotente: si ya estaba compartido no hace nada.
     */
    public function compartirEnlace(string $fileId): void
    {
        try {
            $service = $this->getClient();
            $permiso = new Google_Service_Drive_Permission([
                'type' => 'anyone',
                'role' => 'reader',
            ]);
            $service->permissions->create($fileId, $permiso, ['fields' => 'id']);
        } catch (Exception $e) {
            // 400 "already shared" u otros: se ignora, el enlace sigue válido.
            Log::warning('No se pudo compartir archivo en Drive: '.$e->getMessage());
        }
    }

    /**
     * Lista los archivos directos de una carpeta (con paginación).
     *
     * @return list<array{id: string, name: string, link: string, size: int|null}>
     */
    public function listarArchivosEnCarpeta(string $idCarpeta): array
    {
        $service = $this->getClient();
        $archivos = [];
        $pageToken = null;

        do {
            $respuesta = $service->files->listFiles([
                'q' => "'{$idCarpeta}' in parents and trashed = false",
                'fields' => 'nextPageToken, files(id, name, webViewLink, size)',
                'pageSize' => 1000,
                'pageToken' => $pageToken,
            ]);

            foreach ($respuesta->getFiles() as $f) {
                $archivos[] = [
                    'id' => $f->id,
                    'name' => $f->name,
                    'link' => $f->webViewLink,
                    'size' => isset($f->size) ? (int) $f->size : null,
                ];
            }

            $pageToken = $respuesta->getNextPageToken();
        } while ($pageToken !== null);

        return $archivos;
    }

    /**
     * Descarga el contenido binario de un archivo de Drive.
     */
    public function descargarArchivo(string $fileId): string
    {
        $service = $this->getClient();
        $contenido = $service->files->get($fileId, ['alt' => 'media']);

        if ($contenido instanceof ResponseInterface) {
            return (string) $contenido->getBody();
        }

        if (is_object($contenido) && method_exists($contenido, 'getBody')) {
            return (string) $contenido->getBody();
        }

        return (string) $contenido;
    }

    /**
     * Elimina un archivo de Google Drive extrayendo el ID desde su URL.
     */
    public function deleteFromDrive($urlFoto)
    {
        $fileId = $this->extraerIdDrive($urlFoto);

        if ($fileId) {
            try {
                $service = $this->getClient();
                $service->files->delete($fileId);
            } catch (Exception $e) {
                // Falla silenciosamente si el archivo ya no existe en Drive
            }
        }
    }

    /**
     * Cambia el nombre de un archivo existente en Google Drive.
     */
    public function changeFileName($newBaseName, $urlFoto)
    {
        $fileId = $this->extraerIdDrive($urlFoto);

        if ($fileId) {
            try {
                $service = $this->getClient();

                // Solicita los metadatos del archivo a Google Drive para conocer su extensión original
                $file = $service->files->get($fileId, ['fields' => 'fileExtension']);
                $extension = $file->fileExtension;

                $nuevoNombreCompleto = $newBaseName.'.'.$extension;

                $fileMetadata = new Google_Service_Drive_DriveFile([
                    'name' => $nuevoNombreCompleto,
                ]);

                $service->files->update($fileId, $fileMetadata);

                return true;
            } catch (Exception $e) {
                Log::error('Error renombrando archivo en Drive: '.$e->getMessage());

                return false;
            }
        }

        return false;
    }

    /**
     * Extrae el ID único del archivo desde el enlace webViewLink.
     */
    public function extraerIdDrive($url)
    {
        if (preg_match('/\/d\/([a-zA-Z0-9_-]+)/', $url, $matches)) {
            return $matches[1];
        }

        return null;
    }
}
