<?php

namespace app;

use think\template\TagLib as tl;

class TagLib extends tl
{
    protected $tags = [
        "each" => ["attr" => "name,key,value", "close" => true]
    ];

    public function tagEach(array $tag, string $content): string
    {
        $key   = !empty($tag['key'])  ? trim($tag['key'], '$') : 'key';
        $value = !empty($tag['value']) ? trim($tag['value'], '$') : 'item';

        return '<?php foreach('
            . $this->autoBuildVar($tag['name']) . ' as $' . $key . ' => $' . $value . '): ?>'
            . $content
            . '<?php endforeach; ?>';
    }
}
