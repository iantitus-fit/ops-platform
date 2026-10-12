<?php

namespace App\Enums;

/**
 * What an automated message is for. Owner replies and customer texts have no kind.
 */
enum MessageKind: string
{
    case AgentReply = 'agent_reply';
    case HoldingReply = 'holding_reply';
    case HandoffReply = 'handoff_reply';
    case LifeSafety = 'life_safety';
}
