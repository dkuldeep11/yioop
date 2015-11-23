<?php
/**
 * SeekQuarry/Yioop --
 * Open Source Pure PHP Search Engine, Crawler, and Indexer
 *
 * Copyright (C) 2009 - 2015  Chris Pollett chris@pollett.org
 *
 * LICENSE:
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * END LICENSE
 *
 * @author Chris Pollett chris@pollett.org
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009 - 2015
 * @filesource
 */
namespace seekquarry\yioop\models;

/**
 * This is class is used to handle the
 * captcha settings for Yioop
 *
 * @author Chris Pollett
 */
class CaptchaModel extends Model
{
    /**
     * Makes a graphical captcha from the provided text string
     *
     * @param string $captcha_text string to make image captcha from
     * @return string $data_url a data url containing the obfuscated image
     */
    public function makeGraphicalCaptcha($captcha_text)
    {
        $image = @imagecreatetruecolor(195, 35);
        // defines background color, random lines color and text color
        $bg_color = imagecolorallocate($image, mt_rand(0, 255), 255, 0);
        imagefill($image, 0, 0, $bg_color);
        $lines_color = imagecolorallocate($image, 0x99, 0xCC, 0x99);
        $text_color = imagecolorallocate($image, mt_rand(0, 255), 0, 255);
        // draws random lines
        for ($i = 0; $i < 4; $i++) {
            imageline($image, 0, rand(0, 35), 195, rand(0, 35),
                $lines_color);
        }
        $captcha_letter_array = str_split($captcha_text);
        foreach ($captcha_letter_array as $i => $captcha_letter) {
            imagesetthickness($image, 1);
            imagestring($image, 5, 5 + ($i * 35), rand(2, 14),
                $captcha_letter, $text_color);
        }
        // creates image
        ob_start();
        imagejpeg($image);
        $image_data = ob_get_contents();
        ob_end_clean();
        $data_url = "data:image/jpeg;base64," . base64_encode($image_data);
        imagedestroy($image);
        return $data_url;
    }
}
