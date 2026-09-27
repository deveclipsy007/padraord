export function simulateMargin(baseline: { revenueCents: number; costCents: number }, revenueChangeCents: number, costChangeCents: number) {
    const revenueCents = baseline.revenueCents + revenueChangeCents;
    const costCents = baseline.costCents + costChangeCents;
    const marginCents = revenueCents - costCents;
    return { revenueCents, costCents, marginCents, deltaCents: marginCents - (baseline.revenueCents - baseline.costCents) };
}
