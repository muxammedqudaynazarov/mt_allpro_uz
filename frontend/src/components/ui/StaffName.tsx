import { upperName } from '@/lib/name'
import { cn } from './cn'

/** Xodimning ismi ("FAMILIYA ISM") — katta harflarda, qoraqalpoq "ı" -> "Í" bilan (CSS uppercase buni bilmaydi). */
export function StaffName({ name, className }: { name: string; className?: string }) {
  return <span className={cn(className)}>{upperName(name)}</span>
}
