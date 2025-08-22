<?php
/**
 * captcha.php
 * 双向叠字验证码 (兼容 PHP 5.6 - 7.3)
 * @author yujianyue(15058593138@qq.com)
 * 调用方式：
 *   <img src="captcha.php" alt="captcha">
 */

session_start();
header("Content-Type: image/png");

// ========== 配置 ==========
$square = 500;  // 最终验证码正方形尺寸(px)
$minChars = 8;
$maxChars = 10;

// 字体路径 (必须支持中文的 TTF/OTF)
$font = 'simkai.ttf'; 
if (!file_exists($font)) {
    $try = array(
        'C:/Windows/Fonts/msyh.ttf'
    );
    foreach ($try as $t) {
        if (file_exists($t)) { $font = $t; break; }
    }
}

// 百家姓简表，可自行扩展为完整版 或 自己的字库
$surname_list = preg_split('//u', '赵钱孙李周吴郑王冯陈褚卫蒋沈韩杨朱秦尤许何吕施张孔曹严华金魏陶姜', -1, PREG_SPLIT_NO_EMPTY);

// ========== 函数 ==========

// 从数组随机取 $n 个汉字
function pick_chars($arr, $n) {
    $out = array();
    $count = count($arr);
    for ($i=0; $i<$n; $i++) {
        $out[] = $arr[mt_rand(0,$count-1)];
    }
    return $out;
}

// 渲染一组汉字 -> 缩放成正方形
function render_text_square($chars, $font, $square) {
    $fontsize = intval($square * 0.85); // 字体尽量大
    $gap = 2;

    // 计算原始总宽度
    $totalW = 0; $charWidths = array();
    foreach ($chars as $c) {
        $box = imagettfbbox($fontsize, 0, $font, $c);
        $w = $box[2] - $box[0];
        $charWidths[] = $w;
        $totalW += $w + $gap;
    }
    $totalH = $fontsize;

    // 生成原始长条图
    $img = imagecreatetruecolor($totalW, $totalH);
    imagesavealpha($img, true);
    $trans = imagecolorallocatealpha($img, 0, 0, 0, 127);
    imagefill($img, 0, 0, $trans);
    $black = imagecolorallocate($img, 135, 135, 135);

    $x = 0;
    foreach ($chars as $i => $c) {
        imagettftext($img, $fontsize, 0, $x, $fontsize, $black, $font, $c);
        $x += $charWidths[$i] + $gap;
    }

    // 缩放到 square x square
    $dst = imagecreatetruecolor($square, $square);
    imagesavealpha($dst, true);
    $trans2 = imagecolorallocatealpha($dst, 0, 0, 0, 127);
    imagefill($dst, 0, 0, $trans2);
    imagecopyresampled($dst, $img, 0, 0, 0, 0, $square, $square, $totalW, $totalH);

    imagedestroy($img);
    return $dst;
}

// ========== 主流程 ==========

// 随机两组汉字
$len1 = mt_rand($minChars,$maxChars);
$len2 = mt_rand($minChars,$maxChars);
$chars1 = pick_chars($surname_list, $len1);
$chars2 = pick_chars($surname_list, $len2);

// 渲染成正方形
$img1 = render_text_square($chars1, $font, $square);
$img2 = render_text_square($chars2, $font, $square);

// 旋转第二组 90°
$bg = imagecolorallocatealpha($img2, 0, 0, 0, 127);
$img2_rot = imagerotate($img2, 90, $bg);
imagesavealpha($img2_rot, true);

// 将旋转后的图缩放/裁剪为 square x square
$w = imagesx($img2_rot);
$h = imagesy($img2_rot);
$dst2 = imagecreatetruecolor($square, $square);
imagesavealpha($dst2, true);
$trans3 = imagecolorallocatealpha($dst2, 0, 0, 0, 127);
imagefill($dst2, 0, 0, $trans3);
$scale = max($square/$w, $square/$h);
$newW = max(1,intval($w*$scale));
$newH = max(1,intval($h*$scale));
$tmp = imagecreatetruecolor($newW,$newH);
imagesavealpha($tmp,true);
$trans4 = imagecolorallocatealpha($tmp,0,0,0,127);
imagefill($tmp,0,0,$trans4);
imagecopyresampled($tmp,$img2_rot,0,0,0,0,$newW,$newH,$w,$h);
$sx = intval(($newW-$square)/2);
$sy = intval(($newH-$square)/2);
imagecopy($dst2,$tmp,0,0,$sx,$sy,$square,$square);
imagedestroy($tmp);
imagedestroy($img2_rot);
$img2 = $dst2;

// 叠加到白底
$final = imagecreatetruecolor($square, $square);
$white = imagecolorallocate($final, 255,255,255);
imagefill($final,0,0,$white);
imagesavealpha($final,true);
imagecopy($final,$img1,0,0,0,0,$square,$square);
imagecopy($final,$img2,0,0,0,0,$square,$square);

// 保存 session (旋转的第二组为真实验证码)
$_SESSION['captcha_text'] = implode('', $chars2);
$_SESSION['captcha_time'] = time();

// 输出
imagepng($final);

// 释放
imagedestroy($img1);
imagedestroy($img2);
imagedestroy($final);
exit;
