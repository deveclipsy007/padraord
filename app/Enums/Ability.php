<?php

namespace App\Enums;

enum Ability: string
{
    case OperateWorkspace = 'operate-workspace';
    case ManageTeam = 'manage-team';
    case ManageAi = 'manage-ai';
    case ViewPilotFeedback = 'view-pilot-feedback';
    case ManageDemo = 'manage-demo';
    case ApproveCommercial = 'approve-commercial';
}
