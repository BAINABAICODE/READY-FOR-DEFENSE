const PAGE_WIDTH = 595
const PAGE_HEIGHT = 842
const MARGIN_X = 48
const TOP = 790
const BOTTOM = 56
const WRAP = 88

function pdfText(value) {
  return String(value ?? '')
    .replace(/\u2013|\u2014/g, '-')
    .replace(/\u2018|\u2019/g, "'")
    .replace(/\u201c|\u201d/g, '"')
    .replace(/[^\x09\x0A\x0D\x20-\xFF]/g, '')
    .replace(/\\/g, '\\\\')
    .replace(/\(/g, '\\(')
    .replace(/\)/g, '\\)')
}

function wrapLine(text, width = WRAP) {
  const words = String(text || '').split(/\s+/).filter(Boolean)
  if (!words.length) return ['']
  const lines = []
  let current = ''
  words.forEach((word) => {
    const next = current ? `${current} ${word}` : word
    if (next.length > width && current) {
      lines.push(current)
      current = word
    } else {
      current = next
    }
  })
  if (current) lines.push(current)
  return lines
}

function expandBlocks(blocks) {
  const lines = []
  blocks.forEach((block) => {
    const type = block.type || 'body'
    const size = type === 'title' ? 18 : type === 'heading' ? 13 : 11
    const gap = type === 'title' ? 26 : type === 'heading' ? 20 : 15
    const font = type === 'body' ? 'F1' : 'F2'
    wrapLine(block.text).forEach((text, index) => {
      lines.push({
        text,
        size,
        font,
        gap: index === 0 ? gap : 14,
      })
    })
    if (type === 'heading') lines.push({ text: '', size: 11, font: 'F1', gap: 4 })
  })
  return lines
}

function paginate(lines) {
  const pages = [[]]
  let y = TOP
  lines.forEach((line) => {
    if (y - line.gap < BOTTOM) {
      pages.push([])
      y = TOP
    }
    y -= line.gap
    pages[pages.length - 1].push({ ...line, y })
  })
  return pages.filter((page) => page.length)
}

function pageStream(pageLines, pageNumber, pageCount) {
  const commands = ['BT']
  pageLines.forEach((line) => {
    if (!line.text) return
    commands.push(`/${line.font} ${line.size} Tf`)
    commands.push(`1 0 0 1 ${MARGIN_X} ${line.y} Tm`)
    commands.push(`(${pdfText(line.text)}) Tj`)
  })
  commands.push('/F1 9 Tf')
  commands.push(`1 0 0 1 ${MARGIN_X} 36 Tm`)
  commands.push(`(${pdfText(`AGAPORA  ·  Page ${pageNumber} of ${pageCount}`)}) Tj`)
  commands.push('ET')
  return commands.join('\n')
}

export function buildSimplePdf(blocks) {
  const pages = paginate(expandBlocks(blocks))
  const objects = {
    1: '<< /Type /Catalog /Pages 2 0 R >>',
    3: '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
    4: '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>',
  }
  const pageRefs = []
  let nextId = 5

  pages.forEach((pageLines, index) => {
    const stream = pageStream(pageLines, index + 1, pages.length)
    const contentId = nextId
    nextId += 1
    const pageId = nextId
    nextId += 1
    objects[contentId] = `<< /Length ${stream.length} >>\nstream\n${stream}\nendstream`
    objects[pageId] = `<< /Type /Page /Parent 2 0 R /MediaBox [0 0 ${PAGE_WIDTH} ${PAGE_HEIGHT}] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents ${contentId} 0 R >>`
    pageRefs.push(`${pageId} 0 R`)
  })

  objects[2] = `<< /Type /Pages /Count ${pageRefs.length} /Kids [${pageRefs.join(' ')}] >>`

  let pdf = '%PDF-1.4\n'
  const offsets = [0]
  const maxId = Math.max(...Object.keys(objects).map(Number))
  for (let id = 1; id <= maxId; id += 1) {
    offsets[id] = pdf.length
    pdf += `${id} 0 obj\n${objects[id] || '<< >>'}\nendobj\n`
  }
  const xref = pdf.length
  pdf += `xref\n0 ${maxId + 1}\n`
  pdf += '0000000000 65535 f \n'
  for (let id = 1; id <= maxId; id += 1) {
    pdf += `${String(offsets[id]).padStart(10, '0')} 00000 n \n`
  }
  pdf += `trailer\n<< /Size ${maxId + 1} /Root 1 0 R >>\nstartxref\n${xref}\n%%EOF`
  return pdf
}

export function downloadSimplePdf({ filename, blocks }) {
  const pdf = buildSimplePdf(blocks)
  const blob = new Blob([pdf], { type: 'application/pdf' })
  const url = URL.createObjectURL(blob)
  const link = document.createElement('a')
  link.href = url
  link.download = filename
  link.click()
  URL.revokeObjectURL(url)
}
