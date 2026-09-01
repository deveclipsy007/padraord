import { ReactNode } from 'react';

type Props = {
    label: string;
    value: string;
    meta: ReactNode;
    tone?: 'default' | 'ai';
    artwork?: string;
    decorator?: ReactNode;
    metaClassName?: string;
};

export function MetricWidget({ label, value, meta, tone = 'default', artwork, decorator, metaClassName = '' }: Props) {
    return (
        <div className={`metric-card${tone === 'ai' ? ' ai-metric' : ''}`}>
            {artwork && <img className="metric-artwork" src={artwork} alt="" aria-hidden="true" />}
            {decorator}
            <span>{label}</span>
            <strong>{value}</strong>
            <small className={metaClassName}>{meta}</small>
        </div>
    );
}
