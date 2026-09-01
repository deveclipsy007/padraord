import { describe, expect, it } from 'vitest';
import { buildAudioUploadPlan, processingLabel } from '../context/upload';
import { renderToStaticMarkup } from 'react-dom/server';
import { createElement } from 'react';
import { SpeakerReview } from '../context/SpeakerReview';

describe('context audio upload', () => {
    it('splits a one-hour compressed recording into resumable chunks without requiring preparation', () => {
        const plan = buildAudioUploadPlan(18 * 1024 * 1024, 24 * 1024 * 1024, 250 * 1024 * 1024, 5 * 1024 * 1024);

        expect(plan.strategy).toBe('direct');
        expect(plan.chunks).toBe(4);
    });

    it('marks oversized provider files for preparation while keeping upload resumable', () => {
        const plan = buildAudioUploadPlan(60 * 1024 * 1024, 24 * 1024 * 1024, 250 * 1024 * 1024, 5 * 1024 * 1024);

        expect(plan.strategy).toBe('prepare');
        expect(plan.chunks).toBe(12);
    });

    it('uses human processing labels instead of backend status names', () => {
        expect(processingLabel('transcribing')).toBe('Transcrevendo e separando participantes');
        expect(processingLabel('review_ready')).toBe('Pronto para sua revisão');
    });

    it('renders speaker naming and source timestamps as a review step', () => {
        const html = renderToStaticMarkup(createElement(SpeakerReview, { caseId: 3, entryId: 8, revision: 0, segments: [
            { id: 10, speaker_key: 'A', speaker_name: null, start_ms: 62000, end_ms: 65000, text: 'Precisamos de iluminação.' },
        ] }));

        expect(html).toContain('Quem participou?');
        expect(html).toContain('Pessoa 1');
        expect(html).toContain('1:02');
        expect(html).toContain('Precisamos de iluminação.');
    });
});
