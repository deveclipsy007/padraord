export type AudioUploadPlan = { strategy: 'direct' | 'prepare' | 'reject'; chunks: number; chunkBytes: number };

export function buildAudioUploadPlan(bytes: number, directMaxBytes: number, uploadMaxBytes: number, chunkBytes: number): AudioUploadPlan {
    const safeChunkBytes = Math.max(1, chunkBytes);
    return {
        strategy: bytes > uploadMaxBytes ? 'reject' : bytes > directMaxBytes ? 'prepare' : 'direct',
        chunks: Math.max(1, Math.ceil(bytes / safeChunkBytes)),
        chunkBytes: safeChunkBytes,
    };
}

const labels: Record<string, string> = {
    uploading: 'Enviando áudio privado',
    inspecting: 'Conferindo arquivo',
    preparing: 'Preparando o áudio',
    queued: 'Na fila de processamento',
    prepared: 'Áudio pronto para transcrição',
    transcribing: 'Transcrevendo e separando participantes',
    transcribed: 'Transcrição concluída',
    extracting: 'Organizando fatos e pendências',
    review_ready: 'Pronto para sua revisão',
    applied: 'Rascunhos confirmados',
    failed: 'Processamento interrompido',
    uncertain: 'Resultado precisa de conferência',
};

export function processingLabel(status: string): string {
    return labels[status] ?? 'Processando contexto';
}
