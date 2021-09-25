<?php

namespace Tests\Lockex1987\Html;

use Lockex1987\Html\HtmlUtils;
use PHPUnit\Framework\TestCase;

class HtmlUtilsTest extends TestCase
{
    /**
     * Các phương thức mà không bắt đầu bằng "test" sẽ bị bỏ qua.
     */
    public function testPurifyHtml(): void
    {
        $a = [
            [
                // Xâu rỗng
                'input' => '',
                'expected' => ''
            ],
            [
                // Có thẻ script
                'input' => 'a <script>alert(1)</script> b',
                'expected' => '<p>a  b</p>'
            ],
            [
                // Có thẻ iframe
                'input' => '<iframe src="abc.html"/>',
                'expected' => ''
            ],
            [
                // Có thẻ iframe
                'input' => '<p>abc<iframe//src=jAva&Tab;script:alert(3)>def</p>',
                'expected' => '<p>abc</p>'
            ],
            [
                // Có thuộc tính onload
                'input' => '<svg onload="alert(document.cookie)"></svg>',
                'expected' => '<svg/>'
            ],
            [
                // Thuộc tính src, style bình thường
                'input' => '<img src="abc.jpg" style="width: 100px"/>',
                'expected' => '<img src="abc.jpg" style="width: 100px"/>'
            ],
            [
                // Có thuộc tính onerror
                // Giá trị thuộc tính không trong dấu nhấy kép
                'input' => '<img src=x onerror=alert(1)//>',
                'expected' => '<img src="x"/>'
            ],
            [
                // Có thuộc tính onload
                // Thẻ g của SVG cũng k được
                'input' => '<svg><g/onload=alert(2)//<p>',
                'expected' => '<svg/>'
            ],
            [
                // Sai cấu trúc
                'input' => '<TABLE><tr><td>HELLO</tr></TABL>',
                'expected' => '<table><tr><td>HELLO</td></tr></table>'
            ],
            [
                // Sai cấu trúc
                'input' => '<UL><li><A HREF=//google.com>click</UL>',
                'expected' => '<ul><li><a href="//google.com">click</a></li></ul>'
            ],
            [
                // Nội dung có tiếng Việt
                'input' => 'Tiếng Việt, Nguyễn Văn Huyên, Cao Thị Thùy Dương, Nguyễn Anh Tuấn',
                'expected' => '<p>Tiếng Việt, Nguyễn Văn Huyên, Cao Thị Thùy Dương, Nguyễn Anh Tuấn</p>'
            ],
            [
                // XHTML
                'input' => '<select name="pet" size="3" multiple>
                    <option selected>mouse</option>
                    <option>bird</option>
                    <option>cat</option>
                </select>',
                'expected' => ''
            ],
            [
                // XHTML
                'input' => '<p></p><p><br /></p>',
                'expected' => '<p/><p><br/></p>'
            ],
            [
                // Thực hiện JS bằng thuộc tính href của thẻ a
                'input' => '<a href="javascript:alert(1);">Link 1</a><a href="http://vnexpress.net">Link 2</a>',
                'expected' => '<a>Link 1</a><a href="http://vnexpress.net">Link 2</a>'
            ],
            [
                // Thẻ form
                'input' => '<form><math><mtext><form><mglyph><style></math><img src onerror=alert(1)></style></mglyph></form></mtext></math></form>',
                'expected' => ''
            ]
        ];

        foreach ($a as $e) {
            $actual = HtmlUtils::purifyHtml($e['input']);
            // echo $actual . PHP_EOL;
            $this->assertEquals($e['expected'], $actual);
        }
    }

    /**
     * Kiểm tra phương thức loại bỏ các thẻ rỗng.
     */
    public function testRemoveEmptyNode(): void
    {
        $a = [
            [
                // Bình thường
                'input' => '<p>Cảm ơn tác giả. Bài viết rất hay.</p>',
                'expected' => '<p>Cảm ơn tác giả. Bài viết rất hay.</p>'
            ],
            [
                // Có nhiều thẻ rỗng nội dung
                'input' => '<p>a</p><p>b</p><p><br/></p><p><br/></p><p><br/></p>',
                'expected' => '<p>a</p><p>b</p>'
            ]
        ];

        foreach ($a as $e) {
            $html = $e['input'];
            $doc = HtmlUtils::loadHtml($html);
            HtmlUtils::removeEmptyNode($doc);
            $actual = HtmlUtils::getBodyXhtml($doc);
            // echo $actual . PHP_EOL;
            $this->assertEquals($e['expected'], $actual);
        }
    }
}
