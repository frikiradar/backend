<?php
 // src/Service/FileUploader.php
namespace App\Service;

use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Imagine\Imagick\Imagine;

class FileUploader
{
    private $targetDirectory;
    private $targetFilename;

    public function __construct($targetDirectory, $targetFilename)
    {
        $this->targetDirectory = $targetDirectory;
        $this->targetFilename = $targetFilename;
    }

    public function upload(UploadedFile $file)
    {
        try {
            $imagine = new Imagine();
            $targetSrc = $this->getTargetDirectory() . '/' . $this->getTargetFilename() . '.jpg';

            $response = $imagine->open($file->getRealPath())->save($targetSrc);
            echo $response;
            return $targetSrc;
        } catch (FileException $e) {
            // ... handle exception if something happens during file upload
            print_r($e);
        }
    }

    public function getTargetDirectory()
    {
        return $this->targetDirectory;
    }

    public function getTargetFilename()
    {
        return $this->targetFilename;
    }
}
