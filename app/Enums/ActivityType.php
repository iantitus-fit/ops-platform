<?php

namespace App\Enums;

enum ActivityType: string
{
    case TextReceived = 'text_received';
    case FormReceived = 'form_received';
    case ReplySent = 'reply_sent';
    case Note = 'note';
    case OwnerAlerted = 'owner_alerted';
    case DraftHeld = 'draft_held';
    case IntakeAnswered = 'intake_answered';
    case AgentPaused = 'agent_paused';
    case AgentResumed = 'agent_resumed';
}
