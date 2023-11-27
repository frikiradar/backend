<?php
// src/Service/FileUploaderService.php
namespace App\Service;

use FFMpeg\Filters\Audio\CustomFilter;
use FFMpeg\Filters\Video\ExtractMultipleFramesFilter;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Imagine\Imagick\Imagine;
use Imagine\Image\ImageInterface;
use Imagine\Image\Box;

class FileUploaderService
{
    private $targetDirectory;
    private $targetFilename;

    public function __construct($targetDirectory, $targetFilename)
    {
        $this->targetDirectory = $targetDirectory;
        $this->targetFilename = $targetFilename;
    }

    public function uploadImage(UploadedFile | string $file, $square = true, $quality = 90, $size = 512)
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

            if (!is_string($file)) {
                $file = $file->getRealPath();
            }
            $image = $imagine->open($file);
            if ($square) {
                $image->resize(new Box($size, $size));
            } else {
                $imageSize = $image->getSize();
                $height = $imageSize->getHeight();
                $width = $imageSize->getWidth();
                if ($width > $size) {
                    $newWidth = $size;
                    $newHeigth = $height * $newWidth / $width;
                    $image->resize(new Box($newWidth, $newHeigth));
                }
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

    public function uploadAudio(UploadedFile $file)
    {
        try {
            $targetSrc = $this->getTargetDirectory() . $this->getTargetFilename() . '.mp3';

            if (!file_exists($this->getTargetDirectory())) {
                mkdir($this->getTargetDirectory(), 0777, true);
            }
            $ffmpeg = \FFMpeg\FFMpeg::create([
                'ffmpeg.binaries'  => '../lib/ffmpeg-4.4-amd64-static/ffmpeg',
                'ffprobe.binaries' => '../lib/ffmpeg-4.4-amd64-static/ffprobe',
                'timeout'          => 3600,
                'ffmpeg.threads'   => 12,
            ]);
            $audio = $ffmpeg->open($file->getRealPath());
            // $audio->filters()->custom('arnndn=m=' . '/home/albertoi/frikiradar/backend/lib/ffmpeg-4.4-amd64-static/model/arnndn-models/std.rnnn');
            $audio->filters()->custom('arnndn=m=' . '/var/www/vhosts/frikiradar.com/api.frikiradar.com/lib/ffmpeg-4.4-amd64-static/model/arnndn-models/std.rnnn');
            // $audio->filters()->custom('afftdn=nf=-25');
            // $audio->filters()->custom('highpass=f=200, lowpass=f=3000');
            $audio = $audio->save(new \FFMpeg\Format\Audio\Mp3(), $targetSrc);
            if ($audio) {
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
