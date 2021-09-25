<?php

namespace Lockex1987\Html;

use DOMDocument;
use DOMNode;
use DOMXPath;

/**
 * Các xử lý thường có với HTML.
 */
class HtmlUtils
{
    // Danh sách các thẻ cho phép
    // https://www.w3schools.com/TAGS/default.ASP
    private const WHITE_LIST_TAGS = [
        '#text',
        // ------------------
        'a',
        'abbr',
        // 'address',
        'article',
        // 'aside',
        'audio',
        'b',
        'body',
        // 'base',
        'blockquote',
        'br',
        // 'button',
        // 'canvas',
        'caption',
        'cite',
        'code',
        'col',
        'colgroup',
        // 'data',
        // 'datalist',
        'dd',
        'del',
        'details',
        'dfn',
        // 'dialog',
        'div',
        'dl',
        'dt',
        'em',
        // 'embed',
        'fieldset',
        'figcaption',
        'figure',
        'footer',
        // 'form',
        'h1',
        'h2',
        'h3',
        'h4',
        'h5',
        'h6',
        // 'head',
        'header',
        'hr',
        'html',
        'i',
        // 'iframe',
        'img',
        // 'input',
        'ins',
        'kbd',
        'label',
        'legend',
        'li',
        // 'link',
        // 'main',
        // 'map',
        // 'mark',
        // 'meta',
        // 'meter',
        // 'nav',
        // 'noscript',
        // 'object',
        'ol',
        'optgroup',
        // 'option',
        'output',
        'p',
        'param',
        'picture',
        'pre',
        // 'progress',
        'q',
        'rp',
        'rt',
        'ruby',
        's',
        'samp',
        // 'script',
        'section',
        // 'select',
        'small',
        'source',
        'span',
        'strong',
        'style',
        'sub',
        'summary',
        'sup',
        'svg',
        'table',
        'tbody',
        'td',
        'template',
        // 'textarea',
        'tfoot',
        'th',
        'thead',
        'time',
        'title',
        'tr',
        'track',
        'u',
        'ul',
        'var',
        'video',
        'wbr',
    ];

    // Danh sách các thuộc tính cho phép
    // XSS phải bỏ thuộc tính onload
    private const WHITE_LIST_ATTRIBUTES = [
        'class',
        'src',
        'href',
        'controls',
        'alt',
        'style',
        'target'
    ];

    /**
     * Load mã HTML.
     */
    public static function loadHtml(string $html): DOMDocument
    {
        $normalizedHtml = mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8');
        $normalizedHtml = str_replace("\r\n", "\n", $normalizedHtml);
        $normalizedHtml = str_replace('&nbsp;', ' ', $normalizedHtml);

        // Ẩn các thông báo lỗi thẻ HTML5 không hợp lệ
        libxml_use_internal_errors(true);

        $doc = new DOMDocument(); // '1.0', 'UTF-8'
        // LIBXML_HTML_NOIMPLIED tắt việc tự thêm các thẻ HTML, BODY
        // LIBXML_HTML_NODEFDTD tắt việc tự thêm doctype nếu không có
        @$doc->loadHTML($normalizedHtml); // , LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD

        return $doc;
    }

    /**
     * Lấy mã XHTML của thẻ body.
     */
    public static function getBodyXhtml(DOMDocument $doc): string
    {
        // Chỉ lấy nội dung của thẻ body
        // Đầu ra định dạng XHTML nên sử dụng saveXML thay cho saveHTML.
        // Nếu lưu kiểu saveXML thì cần xóa ký tự &#13; (\r)
        $bodyTag = $doc->getElementsByTagName('body')->item(0);
        $xhtml = $doc->saveXML($bodyTag);
        $xhtml = str_replace(['<body>', '</body>', '<body/>', '&#13;'], ['', '', '', ''], $xhtml);
        return $xhtml;
    }

    /**
     * Lấy toàn bộ mã XHTML.
     */
    public static function getXhtml(DOMDocument $doc): string
    {
        // Output dạng XHTML
        // Chú ý bị thêm khai báo XML <?xml version="1.0" encoding="UTF-8" standalone="yes">
        // LIBXML_NOXMLDECL nghĩa là bỏ qua khai báo XML khi lưu, thêm ở hàm loadHTML
        // tuy nhiên đang có lỗi (https://bugs.php.net/bug.php?id=47137)
        // Để bỏ khai báo XML, chúng ta có thể replace ở output
        // hoặc chỉ lưu $doc->documentElement (nhưng sẽ bị mất DOCTYPE, phải thêm lại)
        $xhtml = $doc->saveXML($doc->documentElement);
        $xhtml = str_replace('<?xml version="1.0" encoding="UTF-8" standalone="yes"?>', '', $xhtml);
        $xhtml = '<!DOCTYPE html>' . $xhtml;
        return $xhtml;
    }

    /**
     * Bỏ các thẻ nguy hiểm, đề phòng lỗi XSS.
     * Thuật ngữ purifier, purify, sanitize.
     * Sử dụng thư viện kiểu ezyang/htmlpurifier dài quá. Phải cấu hình nhiều. Thư viện này còn sửa các lỗi cấu trúc HTML, tự động bỏ thẻ span mà không có thuộc tính nào,...
     * Sử dụng luôn DOMDocument để loại bỏ các thẻ, thuộc tính không mong muốn.
     * Chú ý:
     * - Cần bỏ cả ảnh đuôi SVG
     * - Ảnh mà có style width theo % thì bị mất, theo px thì không bị mất
     * - Bị mất thuộc tính target="_blank" của link
     * - Cần giới hạn phạm vị href của thẻ A?
     * @return string Mã XHTML
     */
    public static function purifyHtml(string $html): string
    {
        // Xâu rỗng thì trả về luôn
        if (!$html) {
            return $html;
        }

        $doc = self::loadHtml($html);
        self::purifyNode($doc);
        return self::getBodyXhtml($doc);
    }

    /**
     * Purify một node nào đó.
     */
    public static function purifyNode(DOMNode $parentNode)
    {
        $shouldRemove = [];
        foreach ($parentNode->childNodes as $childNode) {
            if (!in_array($childNode->nodeName, self::WHITE_LIST_TAGS)) {
                // echo 'Remove ' . $childNode->nodeName . PHP_EOL;
                // TODO: escape, tạo thẻ p với text là nội dung
                $shouldRemove[] = $childNode;
            } else {
                self::purifyAttribute($childNode);
                if ($childNode->hasChildNodes()) {
                    self::purifyNode($childNode);
                }
            }
        }

        foreach ($shouldRemove as $childNode) {
            $parentNode->removeChild($childNode);
            // TODO: Tạo text node
        }
    }

    /**
     * Purify thuộc tính của node.
     */
    public static function purifyAttribute(DOMNode $node)
    {
        if ($node->hasAttributes()) {
            $attrs = $node->attributes;
            foreach ($attrs as $attr) {
                $attrName = $attr->name;
                if (!in_array($attrName, self::WHITE_LIST_ATTRIBUTES)) {
                    $node->removeAttribute($attrName);
                }

                // Thuộc tính href của thẻ a vẫn có thể thực hiện JS
                if ($attrName == 'href') {
                    $attrValue = strtolower(trim($node->getAttribute($attrName)));
                    if (str_starts_with($attrValue, 'javascript')) {
                        $node->removeAttribute($attrName);
                    }
                }
            }
        }
    }

    /**
     * Clean mã nguồn HTML.
     */
    public static function cleanHtml(string $html): void
    {
        $doc = self::loadHtml($html);

        // self::removeAttributes($doc);

        // replaceItalicWithEm();
        // removeNoHrefLink();

        // unwrapDiv();
        // unwrapSpan();
        // removeThemeColorMetaTag();

        // wrapTextNode();
    }

    /**
     * Xóa các thuộc tính (ví dụ style, class, id).
     * Phương thức này sử dụng black-list, xử lý ngược với purifyAttribute (sử dụng white-list)
     * Sử dụng phương thức removeAttribute(), getAttribute(), hasAttribute().
     */
    private static function removeAttributes(DOMDocument $doc): void
    {
        $attributes = [
            'style',
            'class',
            'id',
            // 'name',
            'ng-if',
            'ng-click',
            'ng-non-bindable',
            'spellcheck',
            'border',
            'cellpadding',
            'cellspacing',
            'data-lazy-type',
            'data-lazy-src',
            'data-lazy-srcset',
            'data-lazy-sizes',
            'data-file',
            'data-filename',
            'data-reactid',
            // 'rel',
            'height',
            'width',
            'alt',
            'scope',
            'srcset'
        ];

        $xpath = new DOMXPath($doc);
        foreach ($attributes as $attr) {
            $nodes = $xpath->query("//*[@$attr]");
            foreach ($nodes as $node) {
                if ($node->hasAttribute($attr)) {
                    // echo $node->getAttribute($attr) . PHP_EOL;
                    $node->removeAttribute($attr);
                }
            }
        }
    }

    /**
     * Xóa các thuộc tính (tương tự phương thức removeAttributes).
     */
    private static function removeAttributesRecursive(DOMNode $node): void
    {
        $attributes = [
            'style',
            'class',
            'id'
        ];
        if ($node->hasAttributes()) {
            $attrs = $node->attributes;
            foreach ($attrs as $attr) {
                if (!in_array($attr->name, $attributes)) {
                    $node->removeAttribute($attr->name);
                }
            }
        }

        foreach ($node->childNodes as $childNode) {
            self::removeAttributesRecursive($childNode);
        }
    }

    /**
     * Xóa các thẻ trống.
     */
    public static function removeEmptyNode(DOMDocument $doc): void
    {
        // Phải hỏi hàm này nhiều lần (có thể do quá nhiều node)
        // for ($i = 0; $i < 5; $i++) {
        // echo $i . PHP_EOL;
        // }

        $bodyTag = $doc->getElementsByTagName('body')->item(0);
        $node = $bodyTag;

        // Xử lý các phần tử con trước
        $markToRemovedNodes = [];
        foreach ($node->childNodes as $childNode) {
            $shouldRemoveChild = self::removeEmptyNodeRecursive($childNode);
            if ($shouldRemoveChild) {
                $markToRemovedNodes[] = $childNode;
            }
        }
        foreach ($markToRemovedNodes as $childNode) {
            $node->removeChild($childNode);
        }
    }

    /**
     * Xóa các phần tử rỗng.
     * @param DOMNode node Node đang xử lý
     * @return bool Trả về true nếu nên xóa
     */
    private static function removeEmptyNodeRecursive(DOMNode $node): bool
    {
        $nodeName = strtolower($node->nodeName);

        // Văn bản
        if ($nodeName == '#text') {
            $content = trim($node->textContent); // $node->nodeValue
            return !$content ? true : false;
        }

        // Ảnh, script, bảng không coi là rỗng
        if (in_array($nodeName, ['img', 'script', 'table'])) {
            return false;
        }

        // Xử lý các phần tử con trước
        $markToRemovedNodes = [];
        foreach ($node->childNodes as $childNode) {
            $shouldRemoveChild = self::removeEmptyNodeRecursive($childNode);
            if ($shouldRemoveChild) {
                $markToRemovedNodes[] = $childNode;
            }
        }
        foreach ($markToRemovedNodes as $childNode) {
            $node->removeChild($childNode);
        }

        $childNodesCount = $node->childNodes->count();
        return $childNodesCount == 0;
    }

    /**
     * Bỏ các thẻ img mà có ảnh định dạng SVG.
     * Có thể có sự kiện onload ở trong đó.
     */
    public static function removeSvgImages(DOMDocument $doc): void
    {
        $images = $doc->getElementsByTagName('img');
        if (count($images) !== 0) {
            foreach ($images as $img) {
                $url = strtolower($img->getAttribute('src'));
                if (str_ends_with($url, '.svg')) {
                    $img->parentNode->removeChild($img);
                }
            }
        }
    }
}
