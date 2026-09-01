export function Skeleton({ width = '100%', height = 16, radius = 8 }: { width?: string | number; height?: number; radius?: number }) {
    return <span className="ui-skeleton" aria-hidden="true" style={{ width, height, borderRadius: radius }} />;
}
