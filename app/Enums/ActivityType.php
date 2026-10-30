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
    case DraftDiscarded = 'draft_discarded';
    case DraftAnswered = 'draft_answered';
    case IntakeAnswered = 'intake_answered';
    case AgentPaused = 'agent_paused';
    case AgentResumed = 'agent_resumed';
    case OptedOut = 'opted_out';
    case OptedIn = 'opted_in';
    case RoomEntry = 'room_entry';
    case ProjectOpened = 'project_opened';
    case ProjectClosed = 'project_closed';
    case ProjectReopened = 'project_reopened';
    case ProjectRenamed = 'project_renamed';
}
