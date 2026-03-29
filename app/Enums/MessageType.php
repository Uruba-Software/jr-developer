<?php

namespace App\Enums;

enum MessageType: string
{
    case Text                = 'text';
    case File                = 'file';
    case InteractiveResponse = 'interactive_response';
    case Command             = 'command';
}
