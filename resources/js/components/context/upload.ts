export type AudioUploadPlan = { strategy: 'direct' | 'prepare' | 'reject'; chunks: number; chunkBytes: number };

import coreURL from '@ffmpeg/core-runtime/ffmpeg-core.js?url';
import wasmURL from '@ffmpeg/core-runtime/ffmpeg-core.wasm?url';

export function buildAudioUploadPlan(bytes: number, directMaxBytes: number, uploadMaxBytes: number, chunkBytes: number): AudioUploadPlan {
    const safeChunkBytes = Math.max(1, chunkBytes);
    return {
        strategy: bytes > uploadMaxBytes ? 'reject' : bytes > directMaxBytes ? 'prepare' : 'direct',
        chunks: Math.max(1, Math.ceil(bytes / safeChunkBytes)),
        chunkBytes: safeChunkBytes,
    };
}

const labels: Record<string, string> = {
    waiting: 'Arquivo preservado · aguardando processamento',
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

let ffmpegPromise: Promise<import('@ffmpeg/ffmpeg').FFmpeg> | null = null;

/** Compresses a long recording in the browser before it reaches the resumable upload.
 * The worker is loaded only when a file exceeds the direct provider limit. */
export async function prepareAudioFile(file: File, onProgress?: (value: number) => void): Promise<File> {
    const [{ FFmpeg }, { fetchFile }] = await Promise.all([import('@ffmpeg/ffmpeg'), import('@ffmpeg/util')]);
    if (!ffmpegPromise) {
        const ffmpeg = new FFmpeg();
        ffmpegPromise = ffmpeg.load({ coreURL, wasmURL }).then(() => ffmpeg);
    }
    const ffmpeg = await ffmpegPromise;
    ffmpeg.on('progress', ({ progress }) => onProgress?.(Math.max(0, Math.min(100, Math.round(progress * 100)))));
    const inputName = `input-${crypto.randomUUID()}.${file.name.split('.').pop() || 'audio'}`;
    const outputName = `prepared-${crypto.randomUUID()}.mp3`;
    await ffmpeg.writeFile(inputName, await fetchFile(file));
    const exitCode = await ffmpeg.exec(['-i', inputName, '-ac', '1', '-ar', '16000', '-c:a', 'libmp3lame', '-b:a', '32k', outputName]);
    if (exitCode !== 0) throw new Error('Não foi possível compactar o áudio neste navegador. Cole a transcrição ou exporte um MP3 menor.');
    const data = await ffmpeg.readFile(outputName);
    await Promise.allSettled([ffmpeg.deleteFile(inputName), ffmpeg.deleteFile(outputName)]);
    const bytes = typeof data === 'string' ? new TextEncoder().encode(data) : data;
    const copy = new Uint8Array(bytes.byteLength);
    copy.set(bytes);
    return new File([copy.buffer], `${file.name.replace(/\.[^.]+$/, '')}.mp3`, { type: 'audio/mpeg' });
}
