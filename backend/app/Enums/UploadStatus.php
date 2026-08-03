<?php

namespace App\Enums;

enum UploadStatus: string
{
    case Requested = 'requested';
    case Uploading = 'uploading';
    case Uploaded = 'uploaded';
    case Verified = 'verified';
    case Completed = 'completed';
    case Expired = 'expired';
}
