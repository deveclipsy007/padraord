# Padrão RD Operational Upgrade Implementation Plan

> **For agentic workers:** Execute inline, one vertical slice at a time. The user explicitly requested no multiagent work.

**Goal:** Make daily priorities, client context, project journey, briefing, pipeline, finance, event operation, visual language and reusable production patterns easier to use.

**Architecture:** Reuse current Laravel/Inertia data and actions. Add derived views in React where no new persistence is needed. Persist reusable task models with a dedicated table and audited endpoints. Keep previews distinct from saved financial or production data.

**Tech Stack:** Laravel, PHP, React, TypeScript, Vitest, PHPUnit, CSS.

---

### 1. Decision surfaces

- [x] Add a tested priority selector for the existing operational queue and show three explained actions on Hoje.
- [x] Enrich the client dossier from existing opportunity and activity records; distinguish relationship, case and project.
- [x] Show real module statuses in a clickable journey map.
- [x] Surface briefing gaps as a one-question-at-a-time review entry point.

### 2. Work surfaces

- [x] Enrich Kanban cards with explicit missing next action and understandable stage movement.
- [x] Add a read-only financial scenario simulator using persisted baseline figures.
- [x] Add a compact mobile day-of-event workspace to production, using existing task status actions.

### 3. Repeatability and visual system

- [x] Add reusable production task models with server validation, preview and explicit application.
- [x] Apply consistent page, card, interaction and responsive styling across upgraded surfaces.

### 4. Verification

- [x] Run targeted tests, typecheck, build and relevant PHP tests.
- [x] Inspect home, project, pipeline, finance and production in a browser at desktop and mobile widths.
