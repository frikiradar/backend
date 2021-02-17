<?php
// src/Service/FileUploader.php
namespace App\Service;

use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Imagine\Imagick\Imagine;
use Imagine\Image\ImageInterface;
use Imagine\Image\Box;

class FileUploader
{
    private $targetDirectory;
    private $targetFilename;

    public function __construct($targetDirectory, $targetFilename)
    {
        $this->targetDirectory = $targetDirectory;
        $this->targetFilename = $targetFilename;
    }

    public function upload(UploadedFile $file, $square = true, $quality = 90)
    {
        try {
            $targetSrc = $this->getTargetDirectory() . $this->getTargetFilename() . '.jpg';

            if (!file_exists($this->getTargetDirectory())) {
                mkdir($this->getTargetDirectory(), 0777, true);
            }

            $imagine = new Imagine();

            $options = array(
                'resolution-units' => ImageInterface::RESOLUTION_PIXELSPERINCH,
                'resolution-x' => 150,
                'resolution-y' => 150,
                'jpeg_quality' => $quality
            );

            $image = $imagine
                ->open($file->getRealPath());
            if ($square) {
                $image->resize(new Box(512, 512));
            } else {
                $size = $image->getSize();
                $height = $size->getHeight();
                $width = $size->getWidth();
                $newWidth = 700;
                $newHeigth = $height * $newWidth / $width;
                $image->resize(new Box($newWidth, $newHeigth));
            }
            $image = $image->save($targetSrc, $options);
            if ($image) {
                return $targetSrc;
            }
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
