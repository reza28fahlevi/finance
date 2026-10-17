<?php

namespace App\Enums;

enum ApprovalAction: string
{
    case Approve = 'APPROVE';
    case Reject = 'REJECT';
}
