import { PropsWithChildren, useEffect, useRef, useId } from 'react';
export function Field({label,children,error}:{label:string;children:React.ReactNode;error?:string}){return <label className="rd-field"><span>{label}</span>{children}{error&&<span role="alert" className="rd-field-error">{error}</span>}</label>;}
export function FilterBar({children}:PropsWithChildren){return <div className="rd-filter-bar" role="search">{children}</div>;}
export function FormActions({children}:PropsWithChildren){return <div className="rd-form-actions">{children}</div>;}
export function FormErrors({errors}:{errors:Record<string,string>}){return <>{Object.entries(errors).map(([k,v])=><p className="rd-field-error" role="alert" key={k}>{v}</p>)}</>;}
export function Drawer({title,open,onClose,children,className = ''}:{title:string;open:boolean;onClose:()=>void;children:React.ReactNode;className?:string}){
 const titleId = useId();
 const ref=useRef<HTMLDialogElement>(null);useEffect(()=>{if(open&&!ref.current?.open)ref.current?.showModal();if(!open&&ref.current?.open)ref.current?.close();},[open]);
 return <dialog ref={ref} aria-labelledby={titleId} className={`rd-drawer ${className}`} onCancel={e=>{e.preventDefault();onClose();}}><header><h2 id={titleId}>{title}</h2><button type="button" className="button button-subtle" onClick={onClose} aria-label="Fechar painel">Fechar ×</button></header>{children}</dialog>;
}
