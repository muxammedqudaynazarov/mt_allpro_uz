import { cn } from './cn'

/** Xodimning ismi ("Familiya Ism") — katta harflarda ko'rsatiladi. */
export function StaffName({ name, className }: { name: string; className?: string }) {
  return <span className={cn('uppercase', className)}>{name}</span>
}
