<?php

namespace App\Enums;

enum CredentialType: string
{
    case Transcript = 'TRANSCRIPT';
    case ProgramCertificate = 'PROGRAM_CERTIFICATE';
    case StandaloneCertificate = 'STANDALONE_CERTIFICATE';

    /**
     * A general offering-completion certificate (S4): issued for any offering an
     * S4 CompletionResult marks COMPLETED, independent of Course::is_standalone.
     */
    case OfferingCompletion = 'OFFERING_COMPLETION';
}
