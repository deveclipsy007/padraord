import type { ReactNode } from 'react';

export type Segment<T extends string> = { value: T; label: ReactNode; disabled?: boolean };

export function SegmentedControl<T extends string>({ value, segments, onChange, label }: { value: T; segments: Segment<T>[]; onChange: (value: T) => void; label: string }) {
    return (
        <div className="segmented-control" role="group" aria-label={label}>
            {segments.map((segment) => (
                <button key={segment.value} type="button" className={value === segment.value ? 'is-active' : ''} aria-pressed={value === segment.value} disabled={segment.disabled} onClick={() => onChange(segment.value)}>
                    {segment.label}
                </button>
            ))}
        </div>
    );
}
