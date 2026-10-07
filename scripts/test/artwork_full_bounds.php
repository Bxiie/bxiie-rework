<?php

declare(strict_types=1);
require dirname(__DIR__,2).'/vendor/autoload.php';
$service=(new ReflectionClass(App\Tenant\Media\MediaVariantService::class))->newInstanceWithoutConstructor();
$resize=new ReflectionMethod($service,'createResizedImage');
$directory=sys_get_temp_dir().'/artsfolio-bounds-'.bin2hex(random_bytes(5));
mkdir($directory,0700);
try {
    foreach ([[1200,300],[300,1200],[640,640]] as [$width,$height]) {
        $source="$directory/source-$width.png"; $destination="$directory/resized-$width.png";
        $image=imagecreatetruecolor($width,$height);
        $white=imagecolorallocate($image,255,255,255); $red=imagecolorallocate($image,255,0,0);
        imagefill($image,0,0,$white);
        foreach ([[0,0],[0,$height-12],[$width-12,0],[$width-12,$height-12]] as [$x,$y]) imagefilledrectangle($image,$x,$y,$x+11,$y+11,$red);
        imagepng($image,$source); unset($image);
        if (!$resize->invoke($service,$source,$destination,'image/png',600)) throw new RuntimeException('Resize failed');
        [$outWidth,$outHeight]=getimagesize($destination);
        if (abs($outWidth/$outHeight-$width/$height)>0.001) throw new RuntimeException('Variant changed aspect ratio');
        $result=imagecreatefrompng($destination);
        foreach ([[0,0],[0,$outHeight-1],[$outWidth-1,0],[$outWidth-1,$outHeight-1]] as [$x,$y]) {
            if ((imagecolorat($result,$x,$y) & 0xffffff)!==0xff0000) throw new RuntimeException('Variant cropped a corner');
        }
        unset($result);
    }
} finally {
    foreach (glob($directory.'/*') as $file) unlink($file);
    rmdir($directory);
}
echo "Artwork variants preserve aspect ratios and all four corners for landscape, portrait and square images.\n";
