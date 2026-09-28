import { describe, it, expect } from 'vitest';
import { compareVersions, type StoredVersion } from '../control-utils';
const version = (fields: Record<string, unknown>, kind = 'briefing'): StoredVersion => ({
    id: '1',
    kind,
    label: 'Revisão',
    at: '2026-09-27',
    fields,
});
describe('comparação de revisões', () => {
    it('distingue remoção, inclusão e mudança sem depender da ordem de chaves', () => {
        const differences = compareVersions(
            version({ objective: 'A', remove: 'Sim', nested: { a: 1, b: 2 } }),
            version({ objective: 'B', add: 'Novo', nested: { b: 2, a: 1 } }),
        );
        expect(differences).toEqual([
            { key: 'add', before: undefined, after: 'Novo', type: 'Adicionado' },
            { key: 'objective', before: 'A', after: 'B', type: 'Alterado' },
            { key: 'remove', before: 'Sim', after: undefined, type: 'Removido' },
        ]);
    });
    it('não compara documentos de tipos diferentes', () =>
        expect(compareVersions(version({ a: 1 }), version({ a: 2 }, 'contract'))).toEqual([]));
});
