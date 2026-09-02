import { Head, Link, useForm } from "@inertiajs/react";
import {
    Check,
    CircleAlert,
    Layers3,
    PackageCheck,
    Sparkles,
} from "lucide-react";
import { CasePageHeader } from "../components/CasePageHeader";
import { AppLayout } from "../layout";
import { BentoGrid, BentoItem } from "../components/ui/BentoGrid";
import { Surface } from "../components/ui/Surface";
import { StatusBadge } from "../components/ui/StatusBadge";
import { ViabilityDeliverableEvidence } from "../components/ViabilityDeliverableEvidence";

type Deliverable = {
    key: string;
    title: string;
    status: string;
    required: boolean;
    content?: string | null;
    id?: number;
    evidence?: { note?: string } | null;
};
type Project = {
    revision: number;
    modality: string;
    status: string;
    concept?: string | null;
    experience?: string | null;
    technical_assumptions?: string | null;
    estimate_notes?: string | null;
    supplier_needs?: string | null;
    schedule_notes?: string | null;
    references?: string | null;
    deliverables: Deliverable[];
};
type Props = {
    opportunity: { id: number; title: string; clientName: string };
    project: Project | null;
    journey?: {
        viability_status: string;
        management_status: string;
        outcome?: string | null;
    } | null;
    deliverableOptions: Record<string, string>;
};

export default function Feasibility({
    opportunity,
    project,
    journey,
    deliverableOptions,
}: Props) {
    const selected =
        project?.deliverables
            .filter((item) => item.status !== "pending")
            .map((item) => item.key) ?? [];
    const form = useForm({
        revision: project?.revision ?? 0,
        modality: project?.modality ?? "express",
        concept: project?.concept ?? "",
        experience: project?.experience ?? "",
        technical_assumptions: project?.technical_assumptions ?? "",
        estimate_notes: project?.estimate_notes ?? "",
        supplier_needs: project?.supplier_needs ?? "",
        schedule_notes: project?.schedule_notes ?? "",
        references: project?.references ?? "",
        deliverables: selected,
    });
    const lifecycle = useForm({ status: "", note: "" });
    const required =
        form.data.modality === "complete"
            ? Object.keys(deliverableOptions)
            : Object.keys(deliverableOptions).slice(0, 4);
    const progress = Math.round(
        (form.data.deliverables.filter((key) => required.includes(key)).length /
            Math.max(1, required.length)) *
            100,
    );
    return (
        <AppLayout>
            <Head title={`Viabilidade · ${opportunity.title}`} />
            <CasePageHeader
                id={opportunity.id}
                eyebrow="Projeto de Viabilidade"
                title="Transformar contexto em direção"
                client={opportunity.clientName}
                status={journey?.viability_status || "Ainda não contratada"}
                actions={
                    <Link
                        className="button button-subtle"
                        href={`/opportunities/${opportunity.id}/journey`}
                    >
                        Ver decisões da jornada
                    </Link>
                }
            />
            <BentoGrid className="case-module-bento">
                <BentoItem colSpan={2}>
                    <Surface tone="elevated" className="module-hero-v2">
                        <div>
                            <span className="eyebrow">
                                PRONTIDÃO DA ENTREGA
                            </span>
                            <h2>{progress}% do projeto estruturado</h2>
                            <p>
                                Conceito, estimativa e entregáveis permanecem
                                separados do orçamento de execução.
                            </p>
                        </div>
                        <div
                            className="module-progress-ring"
                            style={
                                {
                                    "--progress": `${progress * 3.6}deg`,
                                } as React.CSSProperties
                            }
                        >
                            <strong>{progress}%</strong>
                        </div>
                    </Surface>
                </BentoItem>
                <BentoItem>
                    <Surface>
                        <span className="eyebrow">MODALIDADE</span>
                        <h2>
                            {form.data.modality === "complete"
                                ? "Completo"
                                : "Express"}
                        </h2>
                        <p>{required.length} entregáveis obrigatórios.</p>
                    </Surface>
                </BentoItem>
                <BentoItem>
                    <Surface>
                        <span className="eyebrow">ESTADO</span>
                        <h2>{journey?.viability_status || "Não contratada"}</h2>
                        <StatusBadge
                            tone={
                                journey?.viability_status === "accepted"
                                    ? "success"
                                    : "warning"
                            }
                        >
                            {project?.status || "Rascunho"}
                        </StatusBadge>
                    </Surface>
                </BentoItem>
            </BentoGrid>
            <form
                className="feasibility-layout"
                onSubmit={(event) => {
                    event.preventDefault();
                    form.post(`/opportunities/${opportunity.id}/feasibility`, {
                        preserveScroll: true,
                    });
                }}
            >
                <main className="feasibility-main">
                    <Surface>
                        <div className="panel-heading">
                            <div>
                                <span className="eyebrow">
                                    DIREÇÃO CRIATIVA
                                </span>
                                <h2>Conceito e experiência</h2>
                            </div>
                            <Sparkles size={18} />
                        </div>
                        <label>
                            Conceito
                            <textarea
                                rows={4}
                                value={form.data.concept}
                                onChange={(event) =>
                                    form.setData("concept", event.target.value)
                                }
                                placeholder="Qual é a ideia central do evento?"
                            />
                        </label>
                        <label>
                            Experiência pretendida
                            <textarea
                                rows={4}
                                value={form.data.experience}
                                onChange={(event) =>
                                    form.setData(
                                        "experience",
                                        event.target.value,
                                    )
                                }
                                placeholder="Como o público deve perceber e viver a experiência?"
                            />
                        </label>
                        <label>
                            Referências
                            <textarea
                                rows={3}
                                value={form.data.references}
                                onChange={(event) =>
                                    form.setData(
                                        "references",
                                        event.target.value,
                                    )
                                }
                            />
                        </label>
                    </Surface>
                    <Surface>
                        <div className="panel-heading">
                            <div>
                                <span className="eyebrow">ESTRUTURA</span>
                                <h2>Premissas e estimativa</h2>
                            </div>
                            <Layers3 size={18} />
                        </div>
                        <label>
                            Premissas técnicas
                            <textarea
                                rows={4}
                                value={form.data.technical_assumptions}
                                onChange={(event) =>
                                    form.setData(
                                        "technical_assumptions",
                                        event.target.value,
                                    )
                                }
                            />
                        </label>
                        <label>
                            Estimativa preliminar
                            <textarea
                                rows={4}
                                value={form.data.estimate_notes}
                                onChange={(event) =>
                                    form.setData(
                                        "estimate_notes",
                                        event.target.value,
                                    )
                                }
                                placeholder="Sem confundir estimativa com orçamento aprovado."
                            />
                        </label>
                        <label>
                            Necessidades de fornecedores
                            <textarea
                                rows={4}
                                value={form.data.supplier_needs}
                                onChange={(event) =>
                                    form.setData(
                                        "supplier_needs",
                                        event.target.value,
                                    )
                                }
                            />
                        </label>
                        <label>
                            Cronograma macro
                            <textarea
                                rows={3}
                                value={form.data.schedule_notes}
                                onChange={(event) =>
                                    form.setData(
                                        "schedule_notes",
                                        event.target.value,
                                    )
                                }
                            />
                        </label>
                    </Surface>
                </main>
                <aside className="feasibility-side">
                    <Surface>
                        <label>
                            Modalidade
                            <select
                                value={form.data.modality}
                                onChange={(event) =>
                                    form.setData("modality", event.target.value)
                                }
                            >
                                <option value="express">Express</option>
                                <option value="complete">Completo</option>
                            </select>
                        </label>
                        <div className="deliverable-list">
                            <div className="panel-heading">
                                <div>
                                    <span className="eyebrow">ENTREGÁVEIS</span>
                                    <h2>Checklist do projeto</h2>
                                </div>
                                <PackageCheck size={18} />
                            </div>
                            {Object.entries(deliverableOptions).map(
                                ([key, title]) => {
                                    const isRequired = required.includes(key);
                                    const checked =
                                        form.data.deliverables.includes(key);
                                    const deliverable =
                                        project?.deliverables.find(
                                            (item) => item.key === key,
                                        ) ?? {
                                            key,
                                            title,
                                            status: "pending",
                                            required: isRequired,
                                        };
                                    return (
                                        <div key={key}>
                                            <label className="deliverable-item">
                                                <input
                                                    type="checkbox"
                                                    checked={checked}
                                                    onChange={(event) =>
                                                        form.setData(
                                                            "deliverables",
                                                            event.target.checked
                                                                ? [
                                                                      ...form
                                                                          .data
                                                                          .deliverables,
                                                                      key,
                                                                  ]
                                                                : form.data.deliverables.filter(
                                                                      (item) =>
                                                                          item !==
                                                                          key,
                                                                  ),
                                                        )
                                                    }
                                                />
                                                <span>
                                                    <strong>{title}</strong>
                                                    <small>
                                                        {isRequired
                                                            ? "Obrigatório nesta modalidade"
                                                            : "Opcional"}
                                                    </small>
                                                </span>
                                                {checked && <Check size={16} />}
                                            </label>
                                            <ViabilityDeliverableEvidence
                                                caseId={opportunity.id}
                                                deliverable={deliverable}
                                            />
                                        </div>
                                    );
                                },
                            )}
                        </div>
                        {Object.values(form.errors).map((error, index) => (
                            <p role="alert" className="form-error" key={index}>
                                {error}
                            </p>
                        ))}
                        <div className="impact-notice">
                            <CircleAlert size={16} />
                            <p>
                                Alterar escopo ou estimativa sinalizará
                                orçamento e documentos para revisão.
                            </p>
                        </div>
                        <button
                            className="button button-primary"
                            disabled={form.processing}
                        >
                            {form.processing
                                ? "Salvando…"
                                : "Salvar rascunho da Viabilidade"}
                        </button>
                    </Surface>
                    {project && (
                        <Surface className="viability-lifecycle">
                            <span className="eyebrow">DECISÃO DE ENTREGA</span>
                            <h2>Avançar com evidência humana</h2>
                            <p>
                                Entrega, aceite e encerramento preservam a
                                decisão. Não substituem aprovação comercial.
                            </p>
                            <label>
                                Observação ou evidência
                                <textarea
                                    rows={3}
                                    value={lifecycle.data.note}
                                    onChange={(event) =>
                                        lifecycle.setData(
                                            "note",
                                            event.target.value,
                                        )
                                    }
                                    placeholder="Obrigatória para aceite ou encerramento sem Gestão."
                                />
                            </label>
                            <div className="button-row">
                                {[
                                    [
                                        "in_development",
                                        "Iniciar desenvolvimento",
                                    ],
                                    [
                                        "ready_for_delivery",
                                        "Pronto para entrega",
                                    ],
                                    ["delivered", "Registrar entrega"],
                                    ["accepted", "Registrar aceite"],
                                    [
                                        "closed_without_management",
                                        "Encerrar sem Gestão",
                                    ],
                                ].map(([status, label]) => (
                                    <button
                                        className={
                                            status === "delivered"
                                                ? "button button-primary"
                                                : "button button-subtle"
                                        }
                                        disabled={lifecycle.processing}
                                        key={status}
                                        onClick={() => {
                                            lifecycle.setData("status", status);
                                            lifecycle.post(
                                                "/opportunities/" +
                                                    opportunity.id +
                                                    "/feasibility/lifecycle",
                                                { preserveScroll: true },
                                            );
                                        }}
                                        type="button"
                                    >
                                        {label}
                                    </button>
                                ))}
                            </div>
                            {Object.values(lifecycle.errors).map(
                                (error, index) => (
                                    <p
                                        role="alert"
                                        className="form-error"
                                        key={index}
                                    >
                                        {error}
                                    </p>
                                ),
                            )}
                        </Surface>
                    )}
                </aside>
            </form>
        </AppLayout>
    );
}
