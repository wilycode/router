<?php
declare(strict_types=1);

namespace WilyCode\Router;

enum HttpMethod: string
{
    case Get = 'GET';
    case Post = 'POST';
    case Put = 'PUT';
    case Delete = 'DELETE';
    case Options = 'OPTIONS';
    case Patch = 'PATCH';
    case Head = 'HEAD';
}
