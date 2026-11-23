import type { GroupRef } from './types'

/** Ma'ruzaga birga qo'shiladigan guruhlar soni chegarasi (backend: Lesson::MAX_LECTURE_GROUPS). */
export const MAX_LECTURE_GROUPS = 6

/** Dars (yoki haftalik dars) guruhlari nomi: "101, 102, 103". Ma'ruzada — barcha guruhlar. */
export function groupsLabel(item: { group: GroupRef; groups?: GroupRef[] }): string {
  return item.groups?.length ? item.groups.map((group) => group.name).join(', ') : item.group.name
}
