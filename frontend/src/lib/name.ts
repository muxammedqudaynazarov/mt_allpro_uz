import type { ChangeEvent } from 'react'

/**
 * Odam ismi katta harflarda (backend: App\Support\PersonName).
 * Qoraqalpoq lotin alifbosida (2016) "ı" ning bosh harfi — "Í": CSS `uppercase` va
 * toUpperCase() uni oddiy "I" ga aylantiradi, shuning uchun alohida almashtiriladi.
 * Har bir harf alohida o'zgartiriladi va uzunlik saqlanadi — kursor joyidan siljimaydi.
 */
export function upperName(value: string): string {
  return Array.from(value, (char) => {
    if (char === 'ı') return 'Í'
    const upper = char.toUpperCase()
    return Array.from(upper).length === 1 ? upper : char
  }).join('')
}

/** Ism maydoni uchun onChange: yozilayotgan matn darhol katta harfga o'tadi, kursor o'z joyida qoladi. */
export function upperNameInput(setValue: (value: string) => void) {
  return (event: ChangeEvent<HTMLInputElement>) => {
    const input = event.target
    const { selectionStart, selectionEnd } = input
    const value = upperName(input.value)
    if (value !== input.value) {
      input.value = value
      if (selectionStart !== null) input.setSelectionRange(selectionStart, selectionEnd ?? selectionStart)
    }
    setValue(value)
  }
}
