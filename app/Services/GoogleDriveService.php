<?php

namespace App\Services;

use Google_Client;
use Google_Service_Drive;
use Google_Service_Drive_DriveFile;
use Illuminate\Support\Facades\Log;
use Exception;

class GoogleDriveService
{
    private function getClient()
    {
        $client = new Google_Client();
        $client->setClientId(config('filesystems.disks.google.clientId'));
        $client->setClientSecret(config('filesystems.disks.google.clientSecret'));
        $client->refreshToken(config('filesystems.disks.google.refreshToken'));
        return new Google_Service_Drive($client);
    }

    /**
     * Sube un archivo a Google Drive y retorna la URL pública
     */
    public function uploadToDrive($archivo, $nombreArchivo, $idCarpetaDestino)
    {
        $service = $this->getClient();

        $fileMetadata = new Google_Service_Drive_DriveFile([
            'name' => $nombreArchivo,
            'parents' => [$idCarpetaDestino]
        ]);

        $driveFile = $service->files->create($fileMetadata, [
            'data' => file_get_contents($archivo->getRealPath()),
            'mimeType' => $archivo->getMimeType(),
            'uploadType' => 'multipart',
            'fields' => 'id, webViewLink'
        ]);

        return $driveFile->webViewLink;
    }

    /**
     * Elimina un archivo de Google Drive extrayendo el ID desde su URL
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
     * Cambia el nombre de un archivo existente en Google Drive
     */

    public function changeFileName($newBaseName, $urlFoto)
    {
        $fileId = $this->extraerIdDrive($urlFoto);

        if ($fileId) {
            try {
                $service = $this->getClient();

                //Solicita los metadatos del archivo a Google Drive para conocer su extensión original
                $file = $service->files->get($fileId, ['fields' => 'fileExtension']);
                $extension = $file->fileExtension;

                $nuevoNombreCompleto = $newBaseName . '.' . $extension;

                $fileMetadata = new Google_Service_Drive_DriveFile([
                    'name' => $nuevoNombreCompleto
                ]);

                $service->files->update($fileId, $fileMetadata);
                return true;

            } catch (Exception $e) {
                Log::error('Error renombrando archivo en Drive: ' . $e->getMessage());
                return false;
            }
        }
        return false;
    }
    /**
     * Extrae el ID único del archivo desde el enlace webViewLink
     */
    public function extraerIdDrive($url)
    {
        if (preg_match('/\/d\/([a-zA-Z0-9_-]+)/', $url, $matches)) {
            return $matches[1];
        }
        return null;
    }
}