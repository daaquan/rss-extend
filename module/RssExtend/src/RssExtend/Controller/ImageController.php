<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/ZendSkeletonApplication for the canonical source repository
 * @copyright Copyright (c) 2005-2012 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace RssExtend\Controller;

use RssExtend\Downloader;
use RssExtend\Exception;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;
use RssExtend\Feed;

if (!function_exists('getimagesizefromstring')) {
    function getimagesizefromstring($string_data)
    {
        $uri = 'data://application/octet-stream;base64,' . base64_encode($string_data);
        return getimagesize($uri);
    }
}

class ImageController extends AbstractActionController
{
    public function indexAction()
    {
        $width = 1000;
        $delivered = false;

        $url = base64_decode($this->params()->fromRoute('url'));
        $hash = $this->params()->fromRoute('hash');
        $expectedHash = md5($url . gethostname() . 'RssExtend');

        /* @var \Zend\Cache\Storage\Adapter\Filesystem $cache */
        $cache = $this->getServiceLocator()->get('Zend\Cache\Storage\Adapter\Filesystem');

        $downloader = new Downloader();
        $downloader->setCache($cache);
        $downloader->setSleep(100000, 1000000);

        $url = utf8_decode($url);
        header('X-URL: ' . $url);

        try {
            $image = $downloader->download($url);
            if ($image && $hash == $expectedHash) {
                $finfo = new \finfo(FILEINFO_MIME);
                $mime = explode(';', $finfo->buffer($image));
                if ($mime && isset($mime[0]) && ($mime[0] == 'image/jpeg' || $mime[0] == 'image/png')) {
                    $size = getimagesizefromstring($image);
                    $origWidth = $size[0];
                    $origHeight = $size[1];
                    $width = min($origWidth, $width);
                    $ratio = $origWidth / $origHeight;
                    $height = round($width / $ratio);
                    $origImage = imagecreatefromstring($image);
                    if ($origImage) {
                        header('Content-Type: ' . $mime[0]);
                        $thumbnail = imagecreatetruecolor($width, $height);
                        imagecopyresampled($thumbnail, $origImage, 0, 0, 0, 0, $width, $height, $origWidth, $origHeight);
                        $quality = 50;
                        if ($width < 500) {
                            $quality = 70;
                        }
                        if ($width <= 300) {
                            $quality = 80;
                        }
                        imagejpeg($thumbnail, null, $quality);
                        $delivered = true;
                    }
                }
            }
        } catch (Exception $e) {

        }

        if (!$delivered) {
            if ($url) {
                header("Location: " . $url, true, 302);
            } else {
                header('Content-Type: image/png');
                echo file_get_contents(getcwd() . '/public/images/notFound.png');
            }

        }

        exit;
    }
}