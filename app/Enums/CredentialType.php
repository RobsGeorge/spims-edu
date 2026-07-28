<?php

namespace App\Enums;

enum CredentialType: string
{
    case Transcript = 'TRANSCRIPT';
    case ProgramCertificate = 'PROGRAM_CERTIFICATE';
    case StandaloneCertificate = 'STANDALONE_CERTIFICATE';
}
