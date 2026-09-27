import { useEffect, useId, useLayoutEffect, useMemo, useRef, useState } from 'react'
import { createPortal } from 'react-dom'
import api from '../../api/client'
import { getBirds, getCatalog } from '../../api/catalogs'
import { getSpeciesFormPreview } from '../../assets/species-form/index.js'
import SearchableSelect from '../BirdsManagement/SearchableSelect.jsx'
import { keepApplicableMutationIds, visualBlockReason } from '../../services/genetics/mutationGroundApplicability.js'
import { keepCombinableMutationIds, visualCombinationBlock } from '../../services/genetics/visualMutationCombinations.js'
import {
  baseColorBlock,
  keepCombinableGeneIds,
  splitGeneBlock,
  visualSplitBlock,
} from '../../services/genetics/splitGeneCombinations.js'
import './BreedingWorkflow.css'

const STATUS_LABELS = {
  'not-started': 'Not Started',
  'in-progress': 'In Progress',
  complete: 'Complete',
  warning: 'Warning',
  error: 'Error',
}

const MIN_AGE_MONTHS = 12
const MAX_AGE_MONTHS = 180
const AGE_MONTH_OPTIONS = Array.from(
  { length: MAX_AGE_MONTHS - MIN_AGE_MONTHS + 1 },
  (_, index) => MIN_AGE_MONTHS + index,
)

function ageMonthOptions(current) {
  if (current === '' || current === null || current === undefined) return AGE_MONTH_OPTIONS
  const extra = Number(current)
  if (Number.isFinite(extra) && extra >= 0 && extra <= 600 && !AGE_MONTH_OPTIONS.includes(extra)) {
    return [...AGE_MONTH_OPTIONS, extra].sort((left, right) => left - right)
  }
  return AGE_MONTH_OPTIONS
}

const HINTS = {
  birdId: 'Type a unique name or code for this parent, such as ABC123. The two parents cannot share the same Bird ID.',
  species: 'Choose the lovebird species first. Available colors, mutations, genes, and pairing rules all come from this choice.',
  sex: 'Choose Cock (Male) or Hen (Female). A pair needs one of each, and sex-linked genes depend on this field.',
  age: 'Select age in months. Choices start at 12 months. Age is used for breeding-safety checks, not Mendelian math.',
  baseColor: 'Pick the documented ground color. RBGIA uses this to calculate how color is inherited.',
  visual: 'Pick one mutation, or a combination this species dataset names. Any other mix stays disabled and cannot be analyzed.',
  split: 'Pick hidden or split genes the bird carries but may not show. These can still appear in chicks.',
  grandparents: 'Optional. Add grandparents only if you have them. They help flag close relationships and extra genetic context.',
  select: 'Load a saved bird from Birds Management. The form fills automatically so you do not retype the profile.',
  parent1: 'Enter or load the first parent. Required: Bird ID, species, sex, and age.',
  parent2: 'Enter the other parent. It must be a different bird and the opposite sex.',
  analyze: 'When both sides are complete, Start Analysis checks compatibility and opens the computation result.',
}

const ASSISTS = {
  birdId: 'Unique label for this parent',
  species: 'Required first — unlocks genetics',
  sex: 'One cock and one hen in the pair',
  age: 'Starts at 12 months',
  baseColor: 'Optional, but needed for a full forecast',
  visual: 'Optional. Only a named dataset combination',
  split: 'Optional. Hidden genes the bird carries',
}

function placeHint(rect) {
  const width = Math.min(320, window.innerWidth - 16)
  const height = 140
  const gap = 10
  const pad = 8
  let left = rect.right + gap
  if (left + width > window.innerWidth - pad) left = rect.left - gap - width
  if (left < pad) left = pad
  if (left + width > window.innerWidth - pad) left = Math.max(pad, window.innerWidth - width - pad)

  let top = rect.top + rect.height / 2 - 28
  if (top + height > window.innerHeight - pad) top = window.innerHeight - height - pad
  if (top < pad) top = pad

  return { top: `${Math.round(top)}px`, left: `${Math.round(left)}px`, width: `${width}px` }
}

function FieldHint({ text }) {
  const tipId = useId()
  const buttonRef = useRef(null)
  const tipRef = useRef(null)
  const hideTimer = useRef(null)
  const [hovered, setHovered] = useState(false)
  const [focused, setFocused] = useState(false)
  const [pinned, setPinned] = useState(false)
  const [style, setStyle] = useState(null)
  const visible = hovered || focused || pinned

  const showHover = () => {
    if (hideTimer.current) window.clearTimeout(hideTimer.current)
    setHovered(true)
  }

  const hideHoverSoon = () => {
    if (hideTimer.current) window.clearTimeout(hideTimer.current)
    hideTimer.current = window.setTimeout(() => setHovered(false), 140)
  }

  useEffect(() => () => {
    if (hideTimer.current) window.clearTimeout(hideTimer.current)
  }, [])

  useLayoutEffect(() => {
    if (!visible) return undefined
    const update = () => {
      const rect = buttonRef.current?.getBoundingClientRect()
      if (rect) setStyle(placeHint(rect))
    }
    update()
    window.addEventListener('resize', update)
    window.addEventListener('scroll', update, true)
    return () => {
      window.removeEventListener('resize', update)
      window.removeEventListener('scroll', update, true)
    }
  }, [visible])

  useEffect(() => {
    if (!pinned) return undefined
    const onPointerDown = (event) => {
      if (buttonRef.current?.contains(event.target) || tipRef.current?.contains(event.target)) return
      setPinned(false)
    }
    const onKeyDown = (event) => {
      if (event.key === 'Escape') setPinned(false)
    }
    document.addEventListener('pointerdown', onPointerDown)
    document.addEventListener('keydown', onKeyDown)
    return () => {
      document.removeEventListener('pointerdown', onPointerDown)
      document.removeEventListener('keydown', onKeyDown)
    }
  }, [pinned])

  return (
    <span
      className="breed-hint"
      onMouseEnter={showHover}
      onMouseLeave={hideHoverSoon}
    >
      <button
        type="button"
        ref={buttonRef}
        className="breed-hint__btn"
        aria-label="More information"
        aria-expanded={visible}
        aria-describedby={visible ? tipId : undefined}
        onClick={(event) => {
          event.preventDefault()
          event.stopPropagation()
          setPinned((open) => !open)
          if (hideTimer.current) window.clearTimeout(hideTimer.current)
          setHovered(false)
        }}
        onFocus={() => setFocused(true)}
        onBlur={(event) => {
          if (tipRef.current?.contains(event.relatedTarget)) return
          setFocused(false)
        }}
      >
        ?
      </button>
      {visible && style
        ? createPortal(
            <span
              ref={tipRef}
              id={tipId}
              className="breed-hint__tip"
              role="tooltip"
              style={style}
              onMouseEnter={showHover}
              onMouseLeave={hideHoverSoon}
            >
              {text}
            </span>,
            document.body,
          )
        : null}
    </span>
  )
}

function progressGuidance({ parent1Status, parent2Status, sameBird, sameSex, pairBlocked, analysisComplete }) {
  if (analysisComplete) return 'Analysis is complete. The computation result should open next.'
  if (sameBird) return 'Choose two different birds. The same Bird ID cannot sit on both sides.'
  if (sameSex) return 'A pair needs one Cock (Male) and one Hen (Female). Change one parent’s sex.'
  if (pairBlocked) return 'Fix the pair error below, then you can start analysis.'
  if (parent1Status === 'error') return 'Parent 1 has a problem. Check the red field notes on that card.'
  if (parent2Status === 'error') return 'Parent 2 has a problem. Check the red field notes on that card.'
  if (parent1Status === 'not-started') return 'Start with Parent 1. Load a saved bird or type the profile by hand.'
  if (parent1Status !== 'complete') return 'Finish Parent 1: Bird ID, species, sex, and age are required.'
  if (parent2Status === 'not-started') return 'Parent 1 is ready. Now enter Parent 2 — the other sex.'
  if (parent2Status !== 'complete') return 'Finish Parent 2: Bird ID, species, sex, and age are required.'
  return 'Both parents are ready. Start Analysis to check compatibility and inheritance.'
}

function stepMark(status) {
  if (status === 'complete') return '✓'
  if (status === 'error') return '!'
  if (status === 'ready') return '→'
  return null
}

function PairProgress({ parent1Status, parent2Status, pairBlocked, busy, analysisComplete, guidance, onAnalyze }) {
  const analyzeStatus = analysisComplete
    ? 'complete'
    : busy
      ? 'in-progress'
      : pairBlocked
        ? 'error'
        : parent1Status === 'complete' && parent2Status === 'complete'
          ? 'ready'
          : 'not-started'

  const steps = [
    { id: 'parent1', label: 'Parent 1', detail: STATUS_LABELS[parent1Status] || parent1Status, status: parent1Status, hint: HINTS.parent1 },
    { id: 'parent2', label: 'Parent 2', detail: STATUS_LABELS[parent2Status] || parent2Status, status: parent2Status, hint: HINTS.parent2 },
    {
      id: 'analyze',
      label: 'Analyze',
      detail: analysisComplete ? 'Complete' : busy ? 'Working' : analyzeStatus === 'ready' ? 'Ready' : analyzeStatus === 'error' ? 'Blocked' : 'Waiting',
      status: analyzeStatus,
      hint: HINTS.analyze,
    },
  ]

  const filled =
    analysisComplete || busy
      ? 100
      : (parent1Status === 'complete' ? 50 : parent1Status === 'in-progress' ? 20 : 0) +
        (parent2Status === 'complete' ? 50 : parent2Status === 'in-progress' ? 20 : 0)

  return (
    <section className="breed-progress" aria-label="Pairing progress">
      <div className="breed-progress__track" aria-hidden="true">
        <span className="breed-progress__fill" style={{ width: `${Math.min(100, filled)}%` }} />
      </div>
      <ol className="breed-progress__steps">
        {steps.map((step, index) => (
          <li key={step.id} className={`breed-progress__step is-${step.status}`}>
            <span className="breed-progress__node">
              {stepMark(step.status) || index + 1}
            </span>
            <div className="breed-progress__copy">
              <span className="breed-progress__label">
                {step.label}
                <FieldHint text={step.hint} />
              </span>
              <strong>{step.detail}</strong>
            </div>
          </li>
        ))}
      </ol>
      <p className="breed-progress__guide">{guidance}</p>
      <div className={`breed-progress__float is-${analyzeStatus}`}>
        <button
          type="button"
          className="breed-progress__start"
          disabled={analyzeStatus !== 'ready' || busy || analysisComplete}
          onClick={onAnalyze}
        >
          {busy ? 'Analyzing…' : analysisComplete ? 'Analysis Complete' : 'Start Analysis'}
        </button>
      </div>
    </section>
  )
}

function ParentProgress({ items }) {
  return (
    <ol className="breed-rail" aria-label="Parent form progress">
      {items.map((item, index) => (
        <li key={item.id} className={item.done ? 'is-complete' : item.active ? 'is-progress' : 'is-waiting'}>
          <span className="breed-rail__node">{item.done ? '✓' : index + 1}</span>
          <span className="breed-rail__label">
            {item.label}
            {item.optional ? <em>Optional</em> : null}
            <FieldHint text={item.hint} />
          </span>
          {index < items.length - 1 ? <span className="breed-rail__line" aria-hidden="true" /> : null}
        </li>
      ))}
    </ol>
  )
}

function FieldLabel({ title, hint, assist, error, children, invalid }) {
  return (
    <label className={`breed-input${invalid ? ' is-invalid' : ''}`}>
      <span className="breed-input__title">
        {title}
        <FieldHint text={hint} />
      </span>
      {assist ? <span className="breed-input__assist">{assist}</span> : null}
      {children}
      {error ? <span className="breed-input__error">{error}</span> : null}
    </label>
  )
}

function names(items) {
  const list = (items || []).map((item) => item?.name).filter(Boolean)
  return list.length ? list.join(', ') : 'None recorded'
}

const BASE_COLOR_SWATCHES = [
  ['olive aqua', '#66733a'],
  ['dark aqua', '#1b6b66'],
  ['dark green', '#1b4d28'],
  ['seagreen', '#1c7a5c'],
  ['turquoise df', '#16485c'],
  ['turquoise sf', '#2a7590'],
  ['cobalt blue', '#1c3d86'],
  ['mauve blue', '#5c3d68'],
  ['cobalt', '#274892'],
  ['mauve', '#6a466e'],
  ['teal df', '#143e3c'],
  ['teal sf', '#245e5a'],
  ['teal', '#1a8078'],
  ['blue df', '#142c52'],
  ['blue sf', '#243f78'],
  ['blue2', '#3a68ae'],
  ['blue1', '#4678c2'],
  ['olive', '#6a622c'],
  ['turquoise', '#35b0c0'],
  ['aqua', '#49c4b4'],
  ['blue', '#3a6cb0'],
  ['green', '#2f8f34'],
]

function swatchForBaseColor(color) {
  const name = String(color?.name || '').toLowerCase()
  const series = String(color?.series || '').toLowerCase()
  const match = BASE_COLOR_SWATCHES.find(([key]) => name.includes(key))
    || BASE_COLOR_SWATCHES.find(([key]) => series.includes(key))
  return match ? match[1] : '#7d8478'
}

function speciesLabel(bird) {
  if (!bird?.species) return '—'
  return bird.species.label || bird.species.common_name || '—'
}

const GP_DEFS = [
  { key: 'paternal_grandfather', title: 'Paternal Grandfather' },
  { key: 'paternal_grandmother', title: 'Paternal Grandmother' },
  { key: 'maternal_grandfather', title: 'Maternal Grandfather' },
  { key: 'maternal_grandmother', title: 'Maternal Grandmother' },
]

function emptyGrandparent() {
  return {
    species_id: null,
    base_color_id: null,
    visual_mutation_ids: [],
    split_gene_id: null,
  }
}

function emptyParent() {
  return {
    source_bird_id: null,
    bird_id: '',
    species_id: null,
    sex: '',
    age_months: '',
    base_color_id: null,
    visual_mutation_ids: [],
    split_gene_ids: [],
    grandparents: {
      paternal_grandfather: emptyGrandparent(),
      paternal_grandmother: emptyGrandparent(),
      maternal_grandfather: emptyGrandparent(),
      maternal_grandmother: emptyGrandparent(),
    },
  }
}

function optionSpeciesLabel(option) {
  if (!option) return ''
  if (option.alternate_names) return `${option.common_name} (${option.alternate_names})`
  return option.common_name
}

function forSpecies(items, speciesId) {
  if (!speciesId) return []
  return items.filter((item) => String(item.species_id) === String(speciesId))
}

function colorForSpeciesDefault(baseColors, speciesId, currentId) {
  const colors = forSpecies(baseColors, speciesId)
  const current = colors.find((color) => String(color.id) === String(currentId))
  if (current) return current
  return colors.find((color) => color.name === 'Green') || null
}

function genesForParent(splitGenes, speciesId, sex) {
  return forSpecies(splitGenes, speciesId).filter((gene) => {
    if (sex === 'hen' && gene.hen_can_split === false) return false
    if (sex === 'cock' && gene.cock_can_split === false) return false
    return true
  })
}

function ageError(value) {
  if (value === '' || value === null || value === undefined) return "Enter the bird's age in months."
  const age = Number(value)
  if (!Number.isInteger(age) || age < 0 || age > 600) {
    return "Enter a valid age in months from 0 to 600."
  }
  return ''
}

function requiredIssues(parent, label) {
  const fields = {}
  const messages = []
  if (!String(parent.bird_id || '').trim()) {
    fields.bird_id = 'Enter a Bird ID.'
    messages.push(`${label}: Enter a Bird ID.`)
  }
  if (!parent.species_id) {
    fields.species_id = 'Please select a species.'
    messages.push(`${label}: Please select a species.`)
  }
  if (!parent.sex) {
    fields.sex = "Please select the bird's sex."
    messages.push(`${label}: Please select the bird's sex.`)
  }
  const ageMessage = ageError(parent.age_months)
  if (ageMessage) {
    fields.age_months = ageMessage
    messages.push(`${label}: ${ageMessage}`)
  }
  return { fields, messages }
}

function parentPayload(parent) {
  const age = ageError(parent.age_months) ? null : Number(parent.age_months)
  return {
    source_bird_id: parent.source_bird_id,
    bird_id: String(parent.bird_id || '').trim(),
    species_id: parent.species_id,
    sex: parent.sex || null,
    age_months: age,
    base_color_id: parent.base_color_id || null,
    visual_mutation_ids: parent.visual_mutation_ids || [],
    split_gene_ids: parent.split_gene_ids || [],
    grandparents: parent.grandparents,
  }
}

function birdToParent(bird) {
  const grandparents = emptyParent().grandparents
  GP_DEFS.forEach(({ key }) => {
    const record = bird.grandparents?.[key]
    if (!record) return
    grandparents[key] = {
      species_id: record.species_id ?? null,
      base_color_id: record.base_color_id ?? null,
      visual_mutation_ids: record.visual_mutation_ids || (record.visual_mutations || []).map((item) => item.id),
      split_gene_id: record.split_gene_id ?? record.split_genes?.[0]?.id ?? null,
    }
  })

  return {
    source_bird_id: bird.id,
    bird_id: bird.bird_id || '',
    species_id: bird.species_id ?? null,
    sex: bird.sex || '',
    age_months: bird.age_months ?? '',
    base_color_id: bird.base_color_id ?? null,
    visual_mutation_ids: bird.visual_mutation_ids || (bird.visual_mutations || []).map((item) => item.id),
    split_gene_ids: bird.split_gene_ids || (bird.split_genes || []).map((item) => item.id),
    grandparents,
  }
}

function parentStarted(parent) {
  return Boolean(
    String(parent.bird_id || '').trim() ||
      parent.species_id ||
      parent.sex ||
      parent.age_months !== '' ||
      parent.base_color_id ||
      parent.visual_mutation_ids?.length ||
      parent.split_gene_ids?.length ||
      GP_DEFS.some(({ key }) => parent.grandparents?.[key]?.species_id),
  )
}

function selectedById(items, ids) {
  const wanted = new Set((ids || []).map((id) => String(id)))
  return (items || []).filter((item) => wanted.has(String(item.id)))
}

function needsVerification(record) {
  return Boolean(record?.verification_status && String(record.verification_status).toLowerCase().includes('needs verification'))
}

function geneticIssues(parent, catalogs) {
  const fields = {}
  const messages = []
  const warnings = []
  if (!parent.species_id) return { fields, messages, warnings }

  if (parent.base_color_id) {
    const color = selectedById(forSpecies(catalogs.baseColors, parent.species_id), [parent.base_color_id])[0]
    if (!color) {
      fields.base_color_id = 'This base color is not documented for the selected species.'
      messages.push(fields.base_color_id)
    } else {
      const splitBlock = baseColorBlock(
        color,
        selectedById(genesForParent(catalogs.splitGenes, parent.species_id, parent.sex), parent.split_gene_ids),
      )
      if (splitBlock) {
        fields.base_color_id = splitBlock
        messages.push(splitBlock)
      } else if (needsVerification(color)) {
        warnings.push('One or more selected genetic traits have not been scientifically verified.')
      }
    }
  } else if (String(parent.bird_id || '').trim() && parent.sex && !ageError(parent.age_months)) {
    warnings.push('Genetic information is incomplete. Missing: base color.')
  }

  const mutationIds = parent.visual_mutation_ids || []
  if (mutationIds.length) {
    const allowed = forSpecies(catalogs.visualMutations, parent.species_id)
    const selected = selectedById(allowed, mutationIds)
    if (selected.length !== mutationIds.length) {
      fields.visual_mutation_ids = 'This visual mutation is not documented for the selected species.'
      messages.push(fields.visual_mutation_ids)
    } else {
      const color = parent.base_color_id
        ? selectedById(forSpecies(catalogs.baseColors, parent.species_id), [parent.base_color_id])[0]
        : null
      const blocked = selected.map((mutation) => visualBlockReason(color, mutation, selected)).find(Boolean)
      if (blocked) {
        fields.visual_mutation_ids = blocked
        messages.push(blocked)
      }
      const selectedSplits = selectedById(
        genesForParent(catalogs.splitGenes, parent.species_id, parent.sex),
        parent.split_gene_ids,
      )
      const conflict = blocked
        ? ''
        : selected.map((candidate, index) => visualCombinationBlock(candidate, selected.slice(index + 1), parent.sex)).find(Boolean)
          || selected.map((candidate) => visualSplitBlock(candidate, selectedSplits)).find(Boolean)
      if (conflict) {
        fields.visual_mutation_ids = conflict
        messages.push(conflict)
      } else if (selected.some(needsVerification)) {
        warnings.push('One or more selected genetic traits have not been scientifically verified.')
      }
    }
  }

  const geneIds = parent.split_gene_ids || []
  if (geneIds.length) {
    const allowed = genesForParent(catalogs.splitGenes, parent.species_id, parent.sex)
    const selected = selectedById(allowed, geneIds)
    if (selected.length !== geneIds.length) {
      fields.split_gene_ids = parent.sex
        ? "The selected sex-linked gene information is inconsistent with the bird's sex."
        : 'Split/hidden genes must belong to the selected species.'
      messages.push(fields.split_gene_ids)
    } else {
      const color = parent.base_color_id
        ? selectedById(forSpecies(catalogs.baseColors, parent.species_id), [parent.base_color_id])[0]
        : null
      const selectedVisuals = selectedById(
        forSpecies(catalogs.visualMutations, parent.species_id),
        parent.visual_mutation_ids,
      )
      const conflict = selected
        .map((candidate, index) =>
          splitGeneBlock(candidate, selected.slice(index + 1), {
            sex: parent.sex,
            baseColor: color,
            visualMutations: selectedVisuals,
          }),
        )
        .find(Boolean)
      if (conflict) {
        fields.split_gene_ids = conflict
        messages.push(conflict)
      } else if (selected.some(needsVerification)) {
        warnings.push('One or more selected genetic traits have not been scientifically verified.')
      }
    }
  }

  return { fields, messages, warnings }
}

function parentIssues(parent, catalogs, label = 'Parent') {
  const required = requiredIssues(parent, label)
  const genetic = geneticIssues(parent, catalogs)
  const ageInvalid = parent.age_months !== '' && parent.age_months !== null && Boolean(ageError(parent.age_months))
  const fields = { ...required.fields, ...genetic.fields }
  return {
    fields,
    messages: [...required.messages, ...genetic.messages.map((message) => `${label}: ${message}`)],
    warnings: genetic.warnings,
    invalid: ageInvalid || Object.keys(genetic.fields).length > 0,
  }
}

function parentFormStatus(parent, fieldErrors = {}, catalogs, attempted = false) {
  if (!parentStarted(parent) && !Object.keys(fieldErrors).length) return 'not-started'
  const issues = parentIssues(parent, catalogs)
  const stillFlagged = Object.keys(fieldErrors).some((field) => issues.fields[field])
  if (issues.invalid || stillFlagged) return 'error'
  if (attempted && Object.keys(issues.fields).length) return 'error'
  if (Object.keys(issues.fields).length) return 'in-progress'
  return 'complete'
}

function sameBirdPair(parent1, parent2) {
  const left = parentPayload(parent1).bird_id
  const right = parentPayload(parent2).bird_id
  return Boolean(left && right && left.localeCompare(right, undefined, { sensitivity: 'accent' }) === 0)
}

function sameSexPair(parent1, parent2) {
  return Boolean(parent1.sex && parent2.sex && parent1.sex === parent2.sex)
}

function overallFormStatus(parent1, parent2, fieldErrors, validation, catalogs, attempted) {
  const left = parentFormStatus(parent1, fieldErrors.parent1, catalogs, attempted)
  const right = parentFormStatus(parent2, fieldErrors.parent2, catalogs, attempted)

  if (left === 'not-started' && right === 'not-started') return 'not-started'
  if (left === 'error' || right === 'error' || sameBirdPair(parent1, parent2) || sameSexPair(parent1, parent2) || validation?.errors?.length) {
    return 'error'
  }
  if (left !== 'complete' || right !== 'complete') return 'in-progress'

  const formWarnings = [
    ...parentIssues(parent1, catalogs, 'Parent 1').warnings,
    ...parentIssues(parent2, catalogs, 'Parent 2').warnings,
  ]
  if (formWarnings.length || validation?.warnings?.length) return 'warning'
  return 'complete'
}

export default function BreedingWorkflow() {
  const [birds, setBirds] = useState([])
  const [speciesOptions, setSpeciesOptions] = useState([])
  const [baseColors, setBaseColors] = useState([])
  const [visualMutations, setVisualMutations] = useState([])
  const [splitGenes, setSplitGenes] = useState([])
  const [loadError, setLoadError] = useState('')
  const [parent1, setParent1] = useState(emptyParent)
  const [parent2, setParent2] = useState(emptyParent)
  const [fieldErrors, setFieldErrors] = useState({ parent1: {}, parent2: {} })
  const [toasts, setToasts] = useState([])
  const [picker, setPicker] = useState(null)
  const [validation, setValidation] = useState(null)
  const [prediction, setPrediction] = useState(null)
  const [analysisComplete, setAnalysisComplete] = useState(false)
  const [analysisAttempted, setAnalysisAttempted] = useState(false)
  const [busy, setBusy] = useState(false)
  const [formError, setFormError] = useState('')

  const loadCatalogs = () => {
    setLoadError('')
    Promise.all([
      getBirds(),
      getCatalog('/lovebird-species'),
      getCatalog('/base-colors'),
      getCatalog('/visual-mutations'),
      getCatalog('/split-genes'),
    ])
      .then(([birdRes, speciesRes, colorRes, mutationRes, geneRes]) => {
        setBirds(birdRes.data?.data || [])
        setSpeciesOptions(speciesRes.data?.data || [])
        setBaseColors(colorRes.data?.data || [])
        setVisualMutations(mutationRes.data?.data || [])
        setSplitGenes(geneRes.data?.data || [])
      })
      .catch(() => {
        setLoadError('Breeding options could not be loaded. Make sure the server is running, then retry.')
      })
  }

  useEffect(() => {
    loadCatalogs()
  }, [])

  const catalogs = { baseColors, visualMutations, splitGenes }
  const parent1Issues = parentIssues(parent1, catalogs, 'Parent 1')
  const parent2Issues = parentIssues(parent2, catalogs, 'Parent 2')
  const sameBird = sameBirdPair(parent1, parent2)
  const sameSex = sameSexPair(parent1, parent2)
  const pairBlocked = sameBird || sameSex || Boolean(validation?.errors?.length)
  const parent1StatusBase = parentFormStatus(parent1, fieldErrors.parent1, catalogs, analysisAttempted)
  const parent2StatusBase = parentFormStatus(parent2, fieldErrors.parent2, catalogs, analysisAttempted)
  const parent1Status = pairBlocked && parent1StatusBase !== 'not-started' ? 'error' : parent1StatusBase
  const parent2Status = pairBlocked && parent2StatusBase !== 'not-started' ? 'error' : parent2StatusBase
  const overallStatus = overallFormStatus(parent1, parent2, fieldErrors, validation, catalogs, analysisAttempted)
  const guidance = progressGuidance({
    parent1Status,
    parent2Status,
    sameBird,
    sameSex,
    pairBlocked,
    analysisComplete,
  })
  const displayErrors = {
    parent1: {
      ...(parentStarted(parent1) || analysisAttempted ? parent1Issues.fields : {}),
      ...fieldErrors.parent1,
    },
    parent2: {
      ...(parentStarted(parent2) || analysisAttempted ? parent2Issues.fields : {}),
      ...fieldErrors.parent2,
    },
  }
  if (sameBird) {
    displayErrors.parent1.bird_id = 'Parent 1 and Parent 2 cannot be the same bird.'
    displayErrors.parent2.bird_id = 'Parent 1 and Parent 2 cannot be the same bird.'
  }
  if (sameSexPair(parent1, parent2)) {
    displayErrors.parent1.sex = 'A breeding pair requires one Cock (Male) and one Hen (Female).'
    displayErrors.parent2.sex = 'A breeding pair requires one Cock (Male) and one Hen (Female).'
  }

  const showRequiredAlerts = () => {
    const nextErrors = { parent1: { ...parent1Issues.fields }, parent2: { ...parent2Issues.fields } }
    const messages = [...parent1Issues.messages, ...parent2Issues.messages]

    if (sameBird) {
      nextErrors.parent1.bird_id = 'Parent 1 and Parent 2 cannot be the same bird.'
      nextErrors.parent2.bird_id = 'Parent 1 and Parent 2 cannot be the same bird.'
      messages.push('Parent 1 and Parent 2 cannot be the same bird.')
    }
    if (sameSexPair(parent1, parent2)) {
      nextErrors.parent1.sex = 'A breeding pair requires one Cock (Male) and one Hen (Female).'
      nextErrors.parent2.sex = 'A breeding pair requires one Cock (Male) and one Hen (Female).'
      messages.push('A breeding pair requires one Cock (Male) and one Hen (Female).')
    }

    setAnalysisAttempted(true)
    setFieldErrors(nextErrors)
    setToasts(messages.map((message, index) => ({ id: `${Date.now()}-${index}`, message })))
    return messages.length === 0
  }

  const clearFieldError = (parentKey, field) => {
    setFieldErrors((current) => {
      if (!current[parentKey]?.[field]) return current
      const next = { ...current, [parentKey]: { ...current[parentKey] } }
      delete next[parentKey][field]
      return next
    })
  }

  const analyze = async () => {
    if (busy || analysisComplete) return
    if (!showRequiredAlerts()) return

    setBusy(true)
    setFormError('')
    setAnalysisComplete(false)
    try {
      const response = await api.post('/breeding/analyze', {
        parent_1: parentPayload(parent1),
        parent_2: parentPayload(parent2),
      })
      const resultId = response.data?.data?.id
      if (!resultId) {
        setFormError('The analysis was saved, but the result page could not be opened.')
        return
      }

      setAnalysisComplete(true)
      window.location.hash = `computation/${resultId}`
    } catch (error) {
      const payload = error?.response?.data
      const fieldMessages = Object.values(payload?.errors || {}).flat().filter(Boolean)
      const messages = fieldMessages.length
        ? fieldMessages
        : [payload?.message || 'RBGIA could not complete this pairing.']
      setToasts(messages.map((message, index) => ({ id: `${Date.now()}-${index}`, message })))
      setFormError('')
    } finally {
      setBusy(false)
    }
  }

  return (
    <main className="breed breed--workflow">
      <div className="breed__shell">
        <header className="breed__header">
          <div className="breed__header-copy">
            <p className="breed__eyebrow">Breeding / Pairing</p>
            <h1 className="breed__title">Start Breeding</h1>
            <p className="breed__lede">
              Build a pair, then run analysis. Load saved birds or type each parent. Hover the{' '}
              <span className="breed-hint-mark" aria-hidden="true">?</span> marks to see what each field is for.
            </p>
          </div>
        </header>

        <PairProgress
          parent1Status={parent1Status}
          parent2Status={parent2Status}
          pairBlocked={pairBlocked}
          busy={busy}
          analysisComplete={analysisComplete}
          guidance={guidance}
          onAnalyze={analyze}
        />

        {formError ? (
          <div className="breed__alert is-error" role="alert">
            <p>{formError}</p>
          </div>
        ) : null}
        {loadError ? (
          <div className="breed__alert is-error" role="alert">
            <p>{loadError}</p>
            <button type="button" className="breed__btn breed__btn--ghost" onClick={loadCatalogs}>
              Retry
            </button>
          </div>
        ) : null}

        <div className="breed-panel breed-panel--pair" data-status={overallStatus}>
        <div className="breed__parents">
            <ParentForm
              title="Parent 1"
              parentKey="parent1"
              parent={parent1}
              status={parent1Status}
              errors={displayErrors.parent1}
              speciesOptions={speciesOptions}
              baseColors={baseColors}
              visualMutations={visualMutations}
              splitGenes={splitGenes}
              onChange={(next, field) => {
                setParent1(next)
                if (field && !parentIssues(next, catalogs, 'Parent 1').fields[field]) clearFieldError('parent1', field)
                setValidation(null)
                setPrediction(null)
                setAnalysisComplete(false)
              }}
              onSelect={() => setPicker('parent1')}
            />
            <ParentForm
              title="Parent 2"
              parentKey="parent2"
              parent={parent2}
              status={parent2Status}
              errors={displayErrors.parent2}
              speciesOptions={speciesOptions}
              baseColors={baseColors}
              visualMutations={visualMutations}
              splitGenes={splitGenes}
              onChange={(next, field) => {
                setParent2(next)
                if (field && !parentIssues(next, catalogs, 'Parent 2').fields[field]) clearFieldError('parent2', field)
                setValidation(null)
                setPrediction(null)
                setAnalysisComplete(false)
              }}
              onSelect={() => setPicker('parent2')}
            />
        </div>
        {sameBird ? (
          <p className="breed-finding is-error">Parent 1 and Parent 2 cannot be the same bird. Change one Bird ID or pick another saved bird.</p>
        ) : null}
        {sameSex ? (
          <p className="breed-finding is-error">A pair needs one Cock (Male) and one Hen (Female). Change the sex on one parent.</p>
        ) : null}
        </div>

        {validation ? <CompatibilityPanel compatibility={validation.compatibility} /> : null}
        {validation ? <GeneticsPanel validation={validation} /> : null}
        {validation ? <WarningsPanel validation={validation} /> : null}
        {prediction ? <PredictionPanel prediction={prediction} validation={validation} /> : null}

      </div>

      {picker ? (
        <BirdPicker
          birds={birds}
          takenId={picker === 'parent1' ? parent2.source_bird_id : parent1.source_bird_id}
          takenCode={picker === 'parent1' ? parent2.bird_id : parent1.bird_id}
          onClose={() => setPicker(null)}
          onSelect={(bird) => {
            const next = birdToParent(bird)
            if (picker === 'parent1') {
              setParent1(next)
              setFieldErrors((current) => ({ ...current, parent1: {} }))
            } else {
              setParent2(next)
              setFieldErrors((current) => ({ ...current, parent2: {} }))
            }
            setValidation(null)
            setPrediction(null)
            setAnalysisComplete(false)
            setPicker(null)
          }}
        />
      ) : null}
      {toasts.length
        ? createPortal(
            <div className="breed-toasts breed-toasts--forest" role="alert" aria-live="assertive">
              {toasts.map((toast) => (
                <div key={toast.id} className="breed-toast">
                  <p>{toast.message}</p>
                  <button
                    type="button"
                    onClick={() => setToasts((current) => current.filter((item) => item.id !== toast.id))}
                  >
                    Dismiss
                  </button>
                </div>
              ))}
            </div>,
            document.body,
          )
        : null}
    </main>
  )
}

function ParentForm({
  title,
  parent,
  status,
  errors = {},
  speciesOptions,
  baseColors,
  visualMutations,
  splitGenes,
  onChange,
  onSelect,
}) {
  const colors = forSpecies(baseColors, parent.species_id)
  const mutations = forSpecies(visualMutations, parent.species_id)
  const genes = genesForParent(splitGenes, parent.species_id, parent.sex)
  const started = parentStarted(parent)
  const mini = [
    {
      id: 'basic',
      label: 'Identity',
      hint: 'Bird ID, species, sex, and age identify this parent and unlock pairing rules.',
      done: !requiredIssues(parent, title).messages.length,
      active: started,
    },
    {
      id: 'genetics',
      label: 'Genetics',
      hint: HINTS.baseColor,
      done: Boolean(parent.base_color_id || parent.visual_mutation_ids.length || parent.split_gene_ids.length),
      active: started,
    },
    {
      id: 'pedigree',
      label: 'Pedigree',
      hint: HINTS.grandparents,
      optional: true,
      done: GP_DEFS.some(({ key }) => parent.grandparents[key]?.species_id),
      active: started,
    },
  ]

  const patch = (field, value, extra = {}) => {
    onChange({ ...parent, [field]: value, ...extra }, field)
  }

  const chooseBaseColor = (next) => {
    const color = colors.find((item) => String(item.id) === String(next)) || null
    const mutationIds = keepApplicableMutationIds(parent.visual_mutation_ids, mutations, color)
    const geneIds = keepCombinableGeneIds(parent.split_gene_ids, genes, {
      sex: parent.sex,
      baseColor: color,
      visualMutations: selectedById(mutations, mutationIds),
    })
    patch('base_color_id', next, { visual_mutation_ids: mutationIds, split_gene_ids: geneIds })
  }

  return (
    <section className="breed-card">
      <div className="breed-card__head">
        <h2>{title}</h2>
        <span className={`breed-status is-${status}`}>{status.replace('-', ' ')}</span>
      </div>
      <ParentProgress items={mini} />
      <div className="breed-card__select">
        <button type="button" className="breed__btn breed__btn--soft" onClick={onSelect}>
          Select Bird from My Birds
        </button>
        <FieldHint text={HINTS.select} />
      </div>

      <div className="breed-form">
        <FieldLabel title="Bird ID" hint={HINTS.birdId} assist={ASSISTS.birdId} error={errors.bird_id} invalid={Boolean(errors.bird_id)}>
          <input
            value={parent.bird_id}
            onChange={(event) => patch('bird_id', event.target.value)}
            placeholder="Example: ABC123"
          />
        </FieldLabel>

        <SearchableSelect
          readable
          label={
            <>
              <span>Species <FieldHint text={HINTS.species} /></span>
              <span className="breed-input__assist">{ASSISTS.species}</span>
            </>
          }
          required
          options={speciesOptions}
          value={parent.species_id}
          error={errors.species_id || ''}
          getOptionLabel={optionSpeciesLabel}
          getOptionValue={(option) => option.id}
          getOptionPreview={(option) => getSpeciesFormPreview(option, parent.sex)}
          allowEmpty={false}
          placeholder="Select species"
          searchPlaceholder="Search species…"
          onChange={(next) => {
            const nextColor = colorForSpeciesDefault(baseColors, next, parent.base_color_id)
            const nextMutations = forSpecies(visualMutations, next)
            const mutationIds = keepApplicableMutationIds(
              (parent.visual_mutation_ids || []).filter((id) =>
                nextMutations.some((item) => String(item.id) === String(id)),
              ),
              nextMutations,
              nextColor,
            )
            const geneIds = keepCombinableGeneIds(
              parent.split_gene_ids,
              genesForParent(splitGenes, next, parent.sex),
              {
                sex: parent.sex,
                baseColor: nextColor,
                visualMutations: selectedById(nextMutations, mutationIds),
              },
            )
            onChange({
              ...parent,
              species_id: next,
              base_color_id: nextColor?.id ?? null,
              visual_mutation_ids: mutationIds,
              split_gene_ids: geneIds,
            }, 'species_id')
          }}
        />

        <FieldLabel title="Sex" hint={HINTS.sex} assist={ASSISTS.sex} error={errors.sex} invalid={Boolean(errors.sex)}>
          <select
            value={parent.sex}
            onChange={(event) => {
              const sex = event.target.value
              const mutationIds = keepCombinableMutationIds(
                parent.visual_mutation_ids,
                forSpecies(visualMutations, parent.species_id),
                sex,
              )
              const color = colors.find((item) => String(item.id) === String(parent.base_color_id)) || null
              const geneIds = keepCombinableGeneIds(
                parent.split_gene_ids,
                genesForParent(splitGenes, parent.species_id, sex),
                {
                  sex,
                  baseColor: color,
                  visualMutations: selectedById(forSpecies(visualMutations, parent.species_id), mutationIds),
                },
              )
              onChange({ ...parent, sex, split_gene_ids: geneIds, visual_mutation_ids: mutationIds }, 'sex')
            }}
          >
            <option value="">Select sex</option>
            <option value="hen">Hen — Female</option>
            <option value="cock">Cock — Male</option>
          </select>
        </FieldLabel>

        <FieldLabel title="Age" hint={HINTS.age} assist={ASSISTS.age} error={errors.age_months} invalid={Boolean(errors.age_months)}>
          <select
            value={parent.age_months === '' || parent.age_months === null ? '' : String(parent.age_months)}
            onChange={(event) => patch('age_months', event.target.value)}
          >
            <option value="">Select months</option>
            {ageMonthOptions(parent.age_months).map((months) => (
              <option key={months} value={String(months)}>
                {months} {months === 1 ? 'month' : 'months'}
              </option>
            ))}
          </select>
        </FieldLabel>

        <SearchableSelect
          readable
          label={
            <>
              <span>Base Color <FieldHint text={HINTS.baseColor} /></span>
              <span className="breed-input__assist">{ASSISTS.baseColor}</span>
            </>
          }
          options={colors}
          value={parent.base_color_id}
          error={errors.base_color_id || ''}
          allowEmpty={false}
          placeholder="Select base color"
          disabled={!parent.species_id}
          searchPlaceholder={parent.species_id ? 'Search base colors…' : 'Select a species first'}
          onChange={chooseBaseColor}
          isOptionDisabled={(option) => baseColorBlock(option, selectedById(genes, parent.split_gene_ids))}
        />

        <SearchableSelect
          readable
          multiple
          label={
            <>
              <span>Visual Mutation <FieldHint text={HINTS.visual} /></span>
              <span className="breed-input__assist">{ASSISTS.visual}</span>
            </>
          }
          options={mutations}
          value={parent.visual_mutation_ids}
          error={errors.visual_mutation_ids || ''}
          allowEmpty={false}
          placeholder="Select mutation"
          disabled={!parent.species_id}
          searchPlaceholder={parent.species_id ? 'Search mutations…' : 'Select a species first'}
          isOptionDisabled={(option) => {
            const color = colors.find((item) => String(item.id) === String(parent.base_color_id)) || null
            const selected = mutations.filter((item) =>
              (parent.visual_mutation_ids || []).some((id) => String(id) === String(item.id)),
            )
            const ground = visualBlockReason(color, option, selected)
            if (ground) return ground
            const combination = visualCombinationBlock(option, selected, parent.sex)
            if (combination) return combination
            return visualSplitBlock(option, selectedById(genes, parent.split_gene_ids))
          }}
          onChange={(next) => {
            const color = colors.find((item) => String(item.id) === String(parent.base_color_id)) || null
            const geneIds = keepCombinableGeneIds(parent.split_gene_ids, genes, {
              sex: parent.sex,
              baseColor: color,
              visualMutations: selectedById(mutations, next),
            })
            patch('visual_mutation_ids', next, { split_gene_ids: geneIds })
          }}
        />

        <SearchableSelect
          readable
          multiple
          label={
            <>
              <span>Split / Hidden Genes <FieldHint text={HINTS.split} /></span>
              <span className="breed-input__assist">{ASSISTS.split}</span>
            </>
          }
          options={genes}
          value={parent.split_gene_ids}
          error={errors.split_gene_ids || ''}
          allowEmpty
          emptyLabel="None"
          disabled={!parent.species_id}
          searchPlaceholder={parent.species_id ? 'Search genes…' : 'Select a species first'}
          isOptionDisabled={(option) =>
            splitGeneBlock(option, selectedById(genes, parent.split_gene_ids), {
              sex: parent.sex,
              baseColor: colors.find((item) => String(item.id) === String(parent.base_color_id)) || null,
              visualMutations: selectedById(mutations, parent.visual_mutation_ids),
            })
          }
          onChange={(next) => patch('split_gene_ids', next)}
        />
      </div>

      <details className="breed-pedigree">
        <summary>
          Grandparents Information <FieldHint text={HINTS.grandparents} />
        </summary>
        {GP_DEFS.map((item) => (
          <GrandparentFields
            key={item.key}
            title={item.title}
            value={parent.grandparents[item.key]}
            speciesOptions={speciesOptions}
            baseColors={baseColors}
            visualMutations={visualMutations}
            splitGenes={splitGenes}
            onChange={(next) =>
              onChange({
                ...parent,
                grandparents: { ...parent.grandparents, [item.key]: next },
              })
            }
          />
        ))}
      </details>
    </section>
  )
}

function GrandparentFields({ title, value, speciesOptions, baseColors, visualMutations, splitGenes, onChange }) {
  return (
    <fieldset className="breed-gp">
      <legend>{title}</legend>
      <SearchableSelect
        label="Species"
        options={speciesOptions}
        value={value.species_id}
        getOptionLabel={optionSpeciesLabel}
        getOptionValue={(option) => option.id}
        getOptionPreview={(option) => getSpeciesFormPreview(option, null)}
        allowEmpty
        emptyLabel="None"
        searchPlaceholder="Search species…"
        onChange={(next) => {
          const color = colorForSpeciesDefault(baseColors, next, value.base_color_id)
          onChange({
            ...value,
            species_id: next,
            base_color_id: color?.id ?? null,
            visual_mutation_ids: [],
            split_gene_id: null,
          })
        }}
      />
      <SearchableSelect
        label="Base Color"
        options={forSpecies(baseColors, value.species_id)}
        value={value.base_color_id}
        allowEmpty
        emptyLabel="None"
        disabled={!value.species_id}
        onChange={(next) => onChange({ ...value, base_color_id: next })}
      />
    </fieldset>
  )
}

function Field({ label, hint, value, wide }) {
  return (
    <div className={wide ? 'breed-fields__wide' : undefined}>
      <dt>
        {label} <FieldHint text={hint} />
      </dt>
      <dd>{value || '—'}</dd>
    </div>
  )
}

function CompatibilityPanel({ compatibility }) {
  if (!compatibility) return null
  return (
    <section className="breed-panel">
      <h2>Species compatibility</h2>
      <p className={`breed-status-banner is-${compatibility.compatibility_status}`}>
        {compatibility.label}
      </p>
      <dl className="breed-summary">
        <div><dt>Status</dt><dd>{compatibility.compatibility_status}</dd></div>
        <div><dt>Breeding type</dt><dd>{compatibility.breeding_type || '—'}</dd></div>
        <div><dt>Fertility status</dt><dd>{compatibility.fertility_status || 'Not documented'}</dd></div>
        <div><dt>Risk level</dt><dd>{compatibility.risk_level || '—'}</dd></div>
        <div><dt>Verification</dt><dd>{compatibility.verification_status || '—'}</dd></div>
        <div><dt>Scientific source</dt><dd>{compatibility.scientific_source || '—'}</dd></div>
      </dl>
      {compatibility.warning_message ? <p>{compatibility.warning_message}</p> : null}
      {compatibility.scientific_basis ? <p>{compatibility.scientific_basis}</p> : null}
    </section>
  )
}

function GeneticsPanel({ validation }) {
  return (
    <section className="breed-panel">
      <h2>Genetic validation</h2>
      <ParentSummary title="Parent 1" parent={validation.parents?.parent_1} />
      <ParentSummary title="Parent 2" parent={validation.parents?.parent_2} />
      <FindingList title="Genetic findings" items={[...validation.errors, ...validation.warnings, ...validation.information].filter((item) => !['breeding_safety', 'incomplete_pedigree', 'age_not_documented'].includes(item.code))} />
    </section>
  )
}

function WarningsPanel({ validation }) {
  return (
    <section className="breed-panel">
      <h2>Breeding warnings</h2>
      <FindingList title="Errors" items={validation.errors} empty="No blocking errors." />
      <FindingList title="Warnings" items={validation.warnings} empty="No warnings." />
      <FindingList title="Information" items={validation.information} empty="No additional notes." />
    </section>
  )
}

function PredictionPanel({ prediction, validation }) {
  return (
    <section className="breed-panel">
      <h2>Final validation summary</h2>
      {validation ? (
        <>
          <ParentSummary title="Parent 1" parent={validation.parents?.parent_1} />
          <ParentSummary title="Parent 2" parent={validation.parents?.parent_2} />
          <CompatibilityPanel compatibility={validation.compatibility} />
        </>
      ) : null}
      <h2>RBGIA result</h2>
      {!prediction ? (
        <p>Start Analysis runs only after required fields are valid. Offspring results come from the saved RBGIA prediction.</p>
      ) : (
        <>
          <p>{prediction.message}</p>
          {(prediction.outcomes || []).map((outcome) => (
            <article key={`${outcome.category}-${outcome.name}`} className="breed-outcome">
              <h3>{outcome.name}</h3>
              <p>{outcome.inheritance_type || outcome.category}</p>
              <p>{outcome.reason || outcome.status}</p>
              {outcome.results?.length ? (
                <ul>
                  {outcome.results.map((row) => (
                    <li key={`${row.sex}-${row.genotype}`}>
                      {row.sex}: {row.genotype} — {row.fraction}
                    </li>
                  ))}
                </ul>
              ) : null}
            </article>
          ))}
        </>
      )}
    </section>
  )
}

function ParentSummary({ title, parent }) {
  if (!parent) return null
  return (
    <div className="breed-summary-block">
      <h3>{title}</h3>
      <dl className="breed-summary">
        <div><dt>Bird ID</dt><dd>{parent.bird_id}</dd></div>
        <div><dt>Species</dt><dd>{parent.species?.common_name || '—'}</dd></div>
        <div><dt>Sex</dt><dd>{parent.sex_label || '—'}</dd></div>
        <div><dt>Age</dt><dd>{parent.age_months} months</dd></div>
        <div><dt>Base Color</dt><dd>{parent.base_color?.name || 'None recorded'}</dd></div>
        <div><dt>Visual Mutations</dt><dd>{names(parent.visual_mutations)}</dd></div>
        <div><dt>Split/Hidden Genes</dt><dd>{names(parent.split_genes)}</dd></div>
      </dl>
    </div>
  )
}

function FindingList({ title, items, empty }) {
  return (
    <div>
      <h3>{title}</h3>
      {items?.length ? (
        <ul className="breed-findings">
          {items.map((item) => (
            <li key={`${item.code}-${item.message}`} className={`breed-finding is-${item.level}`}>
              {item.message}
            </li>
          ))}
        </ul>
      ) : (
        <p>{empty}</p>
      )}
    </div>
  )
}

function BirdPicker({ birds, takenId, takenCode = '', onClose, onSelect }) {
  const [query, setQuery] = useState('')
  const [species, setSpecies] = useState('')
  const [sex, setSex] = useState('')

  const speciesOptions = useMemo(() => {
    const map = new Map()
    birds.forEach((bird) => {
      if (bird.species_id) map.set(String(bird.species_id), speciesLabel(bird))
    })
    return [...map.entries()]
  }, [birds])

  const filtered = birds.filter((bird) => {
    const term = query.trim().toLowerCase()
    const hay = `${bird.bird_id} ${speciesLabel(bird)}`.toLowerCase()
    if (term && !hay.includes(term)) return false
    if (species && String(bird.species_id) !== species) return false
    if (sex && bird.sex !== sex) return false
    return true
  })

  return createPortal(
    <div className="breed-modal" role="presentation">
      <button type="button" className="breed-modal__backdrop" aria-label="Close bird selection" onClick={onClose} />
      <div className="breed-modal__panel" role="dialog" aria-modal="true" aria-labelledby="bird-picker-title">
        <header>
          <h2 id="bird-picker-title">Select Bird from My Birds</h2>
          <button type="button" onClick={onClose}>Close</button>
        </header>
        <div className="breed-modal__filters">
          <label>
            Search by Bird ID
            <FieldHint text="Finds a saved bird by its Bird ID." />
            <input value={query} onChange={(event) => setQuery(event.target.value)} placeholder="Search Bird ID or species" />
          </label>
          <label>
            Search by Species
            <FieldHint text={HINTS.species} />
            <select value={species} onChange={(event) => setSpecies(event.target.value)}>
              <option value="">All species</option>
              {speciesOptions.map(([id, label]) => (
                <option key={id} value={id}>{label}</option>
              ))}
            </select>
          </label>
          <label>
            Filter by Sex
            <FieldHint text={HINTS.sex} />
            <select value={sex} onChange={(event) => setSex(event.target.value)}>
              <option value="">All</option>
              <option value="cock">Cock — Male</option>
              <option value="hen">Hen — Female</option>
            </select>
          </label>
        </div>
        <ul className="breed-picker">
          {filtered.length ? null : <li className="breed-picker__empty">No birds match these filters.</li>}
          {filtered.map((bird) => {
            const preview = getSpeciesFormPreview({ id: bird.species_id, common_name: bird.species?.common_name }, bird.sex)
            const taken =
              bird.id === takenId ||
              (takenCode && bird.bird_id.localeCompare(takenCode, undefined, { sensitivity: 'accent' }) === 0)
            const mutations = (bird.visual_mutations || []).map((item) => item?.name).filter(Boolean)
            const splits = (bird.split_genes || []).map((item) => item?.name).filter(Boolean)
            return (
              <li key={bird.id}>
                <button
                  type="button"
                  className="breed-picker__card"
                  disabled={taken}
                  onClick={() => onSelect(bird)}
                >
                  {preview ? <img src={preview.src} alt="" /> : <span className="breed-picker__ph" aria-hidden="true" />}
                  <span className="breed-picker__copy">
                    <strong className="breed-picker__id">{bird.bird_id}</strong>
                    <span className="breed-picker__species">{speciesLabel(bird)}</span>
                    <span className="breed-picker__meta">{bird.sex_label} · {bird.age_months} months</span>
                    <span className="breed-picker__color">
                      <span
                        className="breed-picker__swatch"
                        style={{ background: swatchForBaseColor(bird.base_color) }}
                        aria-hidden="true"
                      />
                      {bird.base_color?.name || 'No base color'}
                    </span>
                    {mutations.length ? <span className="breed-picker__detail">Mutations: {mutations.join(', ')}</span> : null}
                    {splits.length ? <span className="breed-picker__detail">Splits: {splits.join(', ')}</span> : null}
                  </span>
                  <span className="breed-picker__action">{taken ? 'Already selected' : 'Select'}</span>
                </button>
              </li>
            )
          })}
        </ul>
      </div>
    </div>,
    document.body,
  )
}
