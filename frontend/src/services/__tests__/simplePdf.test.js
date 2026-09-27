import { describe, expect, it } from 'vitest'
import { buildSimplePdf } from '../simplePdf'

describe('buildSimplePdf', () => {
  it('writes a multi-section PDF in the browser without a server call', () => {
    const pdf = buildSimplePdf([
      { type: 'title', text: 'AGAPORA' },
      { type: 'heading', text: 'Result' },
      { type: 'body', text: 'Score: 82 / 100 · Good' },
    ])

    expect(pdf.startsWith('%PDF-1.4')).toBe(true)
    expect(pdf).toContain('AGAPORA')
    expect(pdf).toContain('Score: 82 / 100')
    expect(pdf.trimEnd().endsWith('%%EOF')).toBe(true)
  })
})