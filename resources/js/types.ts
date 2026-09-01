export type OpportunityStage =
    | 'lead'
    | 'qualification'
    | 'briefing'
    | 'budget'
    | 'proposal'
    | 'negotiation'
    | 'contract'
    | 'pre_production'
    | 'production'
    | 'post_event'
    | 'closed'
    | 'lost'
    | 'cancelled';

export type CommercialStage = 'lead' | 'qualification' | 'meeting' | 'initial_briefing' | 'viability_offer' | 'viability_contracted' | 'lost' | 'cancelled';
export type OpportunityPriority = 'low' | 'normal' | 'high';
export type OpportunityOrigin = 'referral' | 'inbound' | 'outbound' | 'returning_client' | 'partner' | 'organic' | 'other';

export type Opportunity = {
    id: number;
    title: string;
    clientName: string;
    stage: OpportunityStage;
    stageLabel: string;
    nextAction: string | null;
    eventDate: string | null;
    estimatedValueCents: number | null;
    briefingStatus: string;
    commercialStage: CommercialStage;
    commercialStageLabel: string;
    ownerId: number | null;
    ownerName: string | null;
    priority: OpportunityPriority;
    origin: OpportunityOrigin;
    nextActionAt?: string | null;
    commercialRevision?: number;
    archived?: boolean;
};

export type PipelineColumn = {
    id: CommercialStage;
    label: string;
    count: number;
    estimatedValueCents?: number;
};

export type QualificationChecklist = {
    revision: number;
    needSummary: string | null;
    decisionMakerStatus: 'unknown' | 'identified' | 'not_applicable';
    decisionMakerContactId: number | null;
    eventDateStatus: 'unknown' | 'estimated' | 'confirmed';
    budgetStatus: 'unknown' | 'range' | 'confirmed';
    fitStatus: 'unknown' | 'low' | 'medium' | 'high';
    notes: string | null;
    status: 'not_started' | 'in_progress' | 'qualified' | 'disqualified';
};

export type WorkQueueItem = {
    id: number;
    kind: 'activity' | 'follow_up' | 'review' | 'case';
    title: string;
    context: string;
    href: string;
    owner: string | null;
    ownerId: number | null;
    dueAt: string | null;
    priority: OpportunityPriority;
    status: string;
    overdue: boolean;
    availableActions: string[];
};

export type PipelineFilters = {
    view: 'kanban' | 'list';
    q: string;
    stage: CommercialStage | '';
    owner: string;
    priority: OpportunityPriority | '';
    origin: OpportunityOrigin | '';
    overdue: boolean;
    unassigned: boolean;
    status: 'active' | 'archived' | 'all';
};

export type BriefingMessage = {
    id: number;
    role: 'user' | 'assistant' | 'system';
    source: 'manual' | 'ai' | 'system' | string;
    body: string;
    createdAt: string;
};

export type CaseModuleKey = 'overview' | 'journey' | 'briefing' | 'viability' | 'budget' | 'documents' | 'production' | 'post_event' | 'history';
export type CaseModuleState = 'empty' | 'draft' | 'needs_review' | 'approved' | 'blocked' | 'complete' | 'stale';

export type CaseModuleStatus = {
    key: CaseModuleKey;
    label: string;
    status: CaseModuleState;
    pending: number;
    href: string;
};

export type CaseWorkspaceSummary = {
    id: number;
    title: string;
    clientName: string;
    ownerName?: string | null;
    phase: OpportunityStage;
    readiness: number;
    nextAction?: string | null;
    dueAt?: string | null;
    modules: CaseModuleStatus[];
};

export type EvidenceReference = {
    entryId: number;
    excerpt: string;
    speaker?: string | null;
    startMs?: number | null;
    endMs?: number | null;
};

export type ContextSegment = {
    id: number;
    speaker: string;
    startMs: number;
    endMs: number;
    text: string;
};

export type ContextEntry = {
    id: number;
    kind: 'text' | 'transcript' | 'audio';
    phase: 'commercial' | 'briefing' | 'technical' | 'production' | 'post_event';
    status: 'received' | 'transcribing' | 'processing' | 'review' | 'applied' | 'failed';
    revision: number;
    body?: string | null;
    segments?: ContextSegment[];
};

export type ModuleChange = {
    module: Exclude<CaseModuleKey, 'overview' | 'journey' | 'history'>;
    field: string;
    current?: string | null;
    suggested: string;
    reason: string;
    kind: 'fact' | 'hypothesis' | 'conflict';
    evidence: EvidenceReference | string;
    impacts: CaseModuleKey[];
};

export type ContextChangeSet = {
    id: number;
    entryId: number;
    status: 'preview' | 'confirmed' | 'stale' | 'rejected';
    actions: ModuleChange[];
};

export type ViabilityWorkspace = {
    revision: number;
    modality: 'express' | 'complete';
    status: 'draft' | 'review' | 'delivered' | 'accepted' | 'closed';
    concept?: string | null;
    experience?: string | null;
    technicalAssumptions?: string | null;
    estimateNotes?: string | null;
};

export type ImpactReference = {
    sourceModule: CaseModuleKey;
    targetModule: CaseModuleKey;
    reason: string;
    status: 'current' | 'stale' | 'resolved';
};
