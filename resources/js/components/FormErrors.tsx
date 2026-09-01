export function FormErrors({ errors }: { errors: Record<string, string> }) {
    return Object.keys(errors).length ? <div role="alert" className="form-error">{Object.entries(errors).map(([key, message]) => <p key={key}>{message}</p>)}</div> : null;
}
