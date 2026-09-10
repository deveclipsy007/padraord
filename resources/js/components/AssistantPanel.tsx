import { Link } from '@inertiajs/react';
import { ArrowUp, Settings2, Sparkles } from 'lucide-react';
import { FormEvent, useEffect, useRef, useState } from 'react';
import { Drawer, Field } from './FormControls';

export type AssistantContext = { opportunity_id: string; supplier_id: string };
export type ActionResult = { demo: boolean; message: string; links: { label: string; href: string }[] };
export type ActionPreview = { id: number; mode: string; status?: string; result?: ActionResult; context: { opportunity_id?: number; supplier_id?: number; case_title?: string; supplier_name?: string }; actions: { kind: string; data: Record<string, string> }[] };
type Turn = { id: number; message: string; reply: string; mode: string; status?: string; cost_usd?: number; preview: ActionPreview | null };
type Settings = { mode: string; status: string; chat_model: string; calculated_usd: number; reserved_usd: number; monthly_usd: number };
const names: Record<string, string> = { 'task.create': 'Criar tarefa', 'supplier.create': 'Cadastrar fornecedor', 'quote.create': 'Registrar cotação', 'inquiry.create': 'Registrar consulta' };
const labels: Record<string, string> = { name: 'Nome', service: 'Serviço', email: 'E-mail', phone: 'Telefone', notes: 'Observações', title: 'Título', description: 'Descrição', due_at: 'Prazo (AAAA-MM-DD)', priority: 'Prioridade (low, normal, high)', unit_cost: 'Preço em reais', price_basis: 'Base do preço (unit ou total)', quantity: 'Quantidade', unit: 'Unidade', valid_until: 'Validade (AAAA-MM-DD)', conditions: 'Condições' };
const usd = (value = 0) => new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'USD', minimumFractionDigits: 4 }).format(value);
async function api(path: string, data?: unknown) {
    const response = await fetch(path, { method: data ? 'POST' : 'GET', headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content || '' }, ...(data ? { body: JSON.stringify(data) } : {}) });
    const body = await response.json().catch(() => ({}));
    if (!response.ok) throw new Error(body.errors ? Object.values(body.errors).flat().join(' ') : 'Não foi possível concluir. Seu texto foi preservado.');
    return body;
}

function Preview({ turn, onUpdate }: { turn: Turn; onUpdate: (preview: ActionPreview) => void }) {
    const preview = turn.preview!;
    const [actions, setActions] = useState(preview.actions);
    const [editing, setEditing] = useState(false);
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState('');
    async function saveOrConfirm() {
        setBusy(true); setError('');
        try {
            if (editing) {
                const updated = await api(`/assistant/chat/${turn.id}/preview`, { preview_id: preview.id, actions });
                onUpdate(updated); setEditing(false);
            } else {
                const result = await api(`/assistant/previews/${preview.id}/confirm`, {});
                onUpdate({ ...preview, status: 'confirmed', result });
            }
        } catch (e) { setError((e as Error).message); }
        finally { setBusy(false); }
    }
    if (preview.status === 'confirmed') return <div className="chat-preview chat-preview--done"><strong>{preview.result?.message || 'Confirmação registrada'}</strong>{preview.result?.links.map(link => <Link key={link.href} href={link.href}>{link.label} →</Link>)}</div>;
    return <section className="chat-preview" aria-label="Prévia das alterações"><strong>Confira antes de confirmar</strong>
        <small>{preview.context.case_title || 'Tarefa interna / sem caso'}{preview.context.supplier_name ? ` · ${preview.context.supplier_name}` : ''}</small>
        {actions.map((action, index) => <div key={index}><h4>{names[action.kind] || action.kind}</h4>{Object.entries(action.data).map(([key, value]) => editing
            ? <Field key={key} label={labels[key] || key}><input value={value} onChange={e => setActions(current => current.map((a, i) => i === index ? { ...a, data: { ...a.data, [key]: e.target.value } } : a))} /></Field>
            : <p key={key}><span>{labels[key] || key}: </span>{value}</p>)}</div>)}
        <small>Nenhuma aprovação comercial ou envio externo será realizado.</small>
        {error && <p role="alert">{error}</p>}
        <div className="chat-preview__actions"><button type="button" className="button button-subtle" disabled={busy} onClick={() => { setActions(preview.actions); setEditing(!editing); }}>{editing ? 'Cancelar edição' : 'Editar'}</button><button type="button" className="button button-primary" disabled={busy} onClick={saveOrConfirm}>{busy ? 'Aguarde…' : editing ? 'Salvar prévia' : preview.mode === 'demo' ? 'Confirmar simulação' : 'Confirmar alterações'}</button></div>
    </section>;
}

export function AssistantPanel({ path, mode }: { path: string; mode: string }) {
    const [open, setOpen] = useState(false);
    const [message, setMessage] = useState('');
    const [turns, setTurns] = useState<Turn[]>([]);
    const [busy, setBusy] = useState(false);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState('');
    const [settings, setSettings] = useState<Settings | null>(null);
    const [canConfigure, setCanConfigure] = useState(false);
    const [context, setContext] = useState<AssistantContext>({ opportunity_id: path.match(/opportunities\/(\d+)/)?.[1] || '', supplier_id: path.match(/suppliers\/(\d+)/)?.[1] || '' });
    const [options, setOptions] = useState<{ opportunities: { id: number; title: string }[]; suppliers: { id: number; name: string }[] }>({ opportunities: [], suppliers: [] });
    const log = useRef<HTMLDivElement>(null);
    const requestId = useRef<string | null>(null);
    useEffect(() => { if (open) log.current?.scrollTo({ top: log.current.scrollHeight }); }, [turns, busy, open]);
    async function load() {
        const data = await api('/assistant/chat');
        setTurns(data.turns); setSettings(data.settings); setCanConfigure(data.canConfigure);
    }
    async function show() {
        setOpen(true); setLoading(true); setError('');
        try { await Promise.all([load(), api('/assistant/context').then(setOptions)]); }
        catch (e) { setError((e as Error).message); }
        finally { setLoading(false); }
    }
    async function send(event?: FormEvent) {
        event?.preventDefault();
        if (!message.trim() || busy) return;
        setBusy(true); setError('');
        const id = requestId.current ?? crypto.randomUUID();
        requestId.current = id;
        try {
            await api('/assistant/chat', { request_id: id, message: message.trim(), opportunity_id: context.opportunity_id || null, supplier_id: context.supplier_id || null });
            setMessage(''); requestId.current = null;
            await load();
        } catch (e) { setError((e as Error).message); try { await load(); } catch { /* Keep the visible conversation. */ } }
        finally { setBusy(false); }
    }
    const activeMode = settings?.mode || mode;
    return <><button type="button" aria-label="Abrir assistente" className="button assistant-trigger" onClick={show}><span aria-hidden="true">✦</span><span>Assistente</span></button>
        <Drawer title="Assistente Padrão RD" open={open} onClose={() => setOpen(false)} className="assistant-chat">
            <div className="chat-status"><span>{activeMode === 'openai' ? 'Luna · OpenAI' : activeMode === 'demo' ? 'Demonstração · sem OpenAI' : 'IA ainda não ativada'}</span>{canConfigure && <Link href="/settings/ai" aria-label="Configurar IA e custos"><Settings2 size={16} /> IA e custos</Link>}</div>
            <details className="chat-context"><summary>Contexto: {options.opportunities.find(o => String(o.id) === context.opportunity_id)?.title || 'Conversa geral'}</summary><Field label="Caso selecionado"><select disabled={busy} value={context.opportunity_id} onChange={e => { setContext({ ...context, opportunity_id: e.target.value }); requestId.current = null; }}><option value="">Conversa geral</option>{options.opportunities.map(o => <option key={o.id} value={o.id}>{o.title}</option>)}</select></Field><Field label="Fornecedor selecionado"><select disabled={busy} value={context.supplier_id} onChange={e => { setContext({ ...context, supplier_id: e.target.value }); requestId.current = null; }}><option value="">Nenhum fornecedor</option>{options.suppliers.map(s => <option key={s.id} value={s.id}>{s.name}</option>)}</select></Field></details>
            <div className="chat-log" ref={log} role="log" aria-label="Conversa com o assistente" aria-live="polite">
                {!turns.length && <div className="chat-welcome"><span className="chat-mark" aria-hidden="true">✦</span><h3>Vamos organizar o próximo passo?</h3><p>Pergunte, traga uma ideia ou peça ajuda com o trabalho. Alterações aparecem para você conferir antes de confirmar.</p>{['Qual é meu próximo passo?', 'Me ajude a organizar um briefing', 'Crie uma tarefa para cobrar retorno'].map(text => <button type="button" key={text} onClick={() => { setMessage(text); requestId.current = null; }}>{text}</button>)}</div>}
                {turns.map(turn => <div className="chat-turn" key={turn.id}><article className="chat-bubble chat-bubble--user"><small>Você</small><p>{turn.message}</p></article><article className="chat-bubble chat-bubble--assistant"><small>✦ {turn.mode === 'openai' ? 'Padrão RD · Luna' : 'Padrão RD · orientação'}</small><p>{turn.reply || 'Pedido recebido. A resposta ainda está em processamento.'}</p>{turn.preview && <Preview key={turn.preview.id} turn={turn} onUpdate={preview => setTurns(current => current.map(t => t.id === turn.id ? { ...t, preview } : t))} />}{turn.cost_usd !== undefined && <small>Custo calculado desta resposta: {usd(turn.cost_usd)}</small>}</article></div>)}
                {(busy || loading) && <p className="chat-thinking" role="status"><Sparkles size={16} /> {loading ? 'Carregando conversa…' : 'Preparando sua resposta…'}</p>}
            </div>
            <form className="chat-composer" onSubmit={send}>{error && <p className="form-error" role="alert">{error}</p>}<label className="sr-only" htmlFor="chat-message">Sua mensagem</label><textarea id="chat-message" rows={2} maxLength={8000} value={message} disabled={busy} placeholder="Pergunte ou peça algo…" onChange={e => { setMessage(e.target.value); requestId.current = null; }} onKeyDown={e => { if (e.key === 'Enter' && !e.shiftKey && !e.nativeEvent.isComposing && !window.matchMedia('(pointer: coarse)').matches) { e.preventDefault(); void send(); } }} /><button type="submit" className="chat-send" disabled={busy || loading || !message.trim()} aria-label="Enviar mensagem"><ArrowUp size={20} /></button><small>Você revisa. O sistema só altera após sua confirmação.</small></form>
            <div className="chat-costs">Este mês: {usd(settings?.calculated_usd)} calculados · {usd(settings?.reserved_usd)} reservados <span>Limite: {usd(settings?.monthly_usd)} · não é a fatura da OpenAI</span></div>
        </Drawer>
    </>;
}
