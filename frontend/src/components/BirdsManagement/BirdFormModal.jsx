import { useEffect, useId, useState } from 'react'
import SearchableSelect from './SearchableSelect.jsx'
import { getSpeciesFormPreview } from '../../assets/species-form/index.js'
import { keepApplicableMutationIds, visualBlockReason } from '../../services/genetics/mutationGroundApplicability.js'
import { keepCombinableMutationIds, visualCombinationBlock } from '../../services/genetics/visualMutationCombinations.js'
import {
  baseColorBlock,
  keepCombinableGeneIds,
  splitGeneBlock,
  visualSplitBlock,
} from '../../services/genetics/splitGeneCombinations.js'
import './BirdFormModal.css'

const SEX_OPTIONS = [
  { value: 'hen', label: 'Hen — Female' },
  { value: 'cock', label: 'Cock — Male' },
]

const MAX_AGE_MONTHS = 180
const AGE_MONTH_OPTIONS = Array.from({ length: MAX_AGE_MONTHS + 1 }, (_, months) => months)

function ageMonthOptions(current) {
  const extra = Number(current)
  if (Number.isFinite(extra) && extra > MAX_AGE_MONTHS) {
    return [...AGE_MONTH_OPTIONS, extra]
  }
  return AGE_MONTH_OPTIONS
}

const GRANDPARENT_DEFS = [
  { key: 'paternal_grandfather', title: 'Paternal Grandfather' },
  { key: 'paternal_grandmother', title: 'Paternal Grandmother' },
  { key: 'maternal_grandfather', title: 'Maternal Grandfather' },
  { key: 'maternal_grandmother', title: 'Maternal Grandmother' },
]

export function createEmptyGrandparent() {
  return {
    species_id: null,
    base_color_id: null,
    visual_mutation_id: null,
    visual_mutation_ids: [],
    split_gene_id: null,
    split_gene_ids: [],
  }
}

export function createEmptyBirdForm() {
  return {
    bird_id: '',
    age_months: '',
    species_id: null,
    sex: '',
    base_color_id: null,
    visual_mutation_id: null,
    visual_mutation_ids: [],
    split_gene_id: null,
    split_gene_ids: [],
    grandparents: {
      paternal_grandfather: createEmptyGrandparent(),
      paternal_grandmother: createEmptyGrandparent(),
      maternal_grandfather: createEmptyGrandparent(),
      maternal_grandmother: createEmptyGrandparent(),
    },
  }
}

export function birdToForm(bird) {
  if (!bird) return createEmptyBirdForm()

  const grandparents = createEmptyBirdForm().grandparents

  GRANDPARENT_DEFS.forEach(({ key }) => {
    const record = bird.grandparents?.[key]
    grandparents[key] = record
      ? {
          species_id: record.species_id ?? null,
          base_color_id: record.base_color_id ?? null,
          visual_mutation_id: record.visual_mutation_id ?? null,
          visual_mutation_ids: mutationIdsFromRecord(record),
          split_gene_id: record.split_gene_id ?? null,
          split_gene_ids: geneIdsFromRecord(record),
        }
      : createEmptyGrandparent()
  })

  return {
    bird_id: bird.bird_id ?? '',
    age_months: bird.age_months ?? '',
    species_id: bird.species_id ?? null,
    sex: bird.sex ?? '',
    base_color_id: bird.base_color_id ?? null,
    visual_mutation_id: bird.visual_mutation_id ?? null,
    visual_mutation_ids: mutationIdsFromRecord(bird),
    split_gene_id: bird.split_gene_id ?? null,
    split_gene_ids: geneIdsFromRecord(bird),
    grandparents,
  }
}

function speciesLabel(option) {
  if (!option) return ''
  if (option.alternate_names) {
    return `${option.common_name} (${option.alternate_names})`
  }
  return option.common_name
}

function colorsForSpecies(baseColors, speciesId) {
  if (!speciesId) return []
  return baseColors.filter((color) => String(color.species_id) === String(speciesId))
}

function colorForSpeciesDefault(baseColors, speciesId, currentId) {
  const colors = colorsForSpecies(baseColors, speciesId)
  const current = colors.find((color) => String(color.id) === String(currentId))
  if (current) return current
  return colors.find((color) => color.name === 'Green') || null
}

function genesForBird(splitGenes, speciesId, sex) {
  if (!speciesId) return []
  return splitGenes.filter((gene) => {
    if (String(gene.species_id) !== String(speciesId)) return false
    if (sex === 'hen' && gene.hen_can_split === false) return false
    if (sex === 'cock' && gene.cock_can_split === false) return false
    return true
  })
}

function geneIdsFromRecord(record) {
  if (Array.isArray(record?.split_gene_ids) && record.split_gene_ids.length) {
    return record.split_gene_ids.filter((id) => id !== null && id !== '')
  }
  if (Array.isArray(record?.split_genes) && record.split_genes.length) {
    return record.split_genes.map((gene) => gene.id)
  }
  if (record?.split_gene_id) return [record.split_gene_id]
  return []
}

function keepGeneIds(ids, splitGenes, speciesId, sex, baseColor, visualMutations) {
  return keepCombinableGeneIds(ids, genesForBird(splitGenes, speciesId, sex), {
    sex,
    baseColor,
    visualMutations,
  })
}

function selectedMutationsFor(visualMutations, speciesId, ids) {
  return mutationsForSpecies(visualMutations, speciesId).filter((item) =>
    (ids || []).some((id) => String(id) === String(item.id)),
  )
}

function selectedGenesFor(splitGenes, speciesId, sex, ids) {
  return genesForBird(splitGenes, speciesId, sex).filter((item) =>
    (ids || []).some((id) => String(id) === String(item.id)),
  )
}

function sexForGrandparent(role) {
  if (String(role).includes('grandmother')) return 'hen'
  if (String(role).includes('grandfather')) return 'cock'
  return null
}

function visualMutationBlock(option, selected, baseColor, sex, splits) {
  const ground = visualBlockReason(baseColor, option, selected)
  if (ground) return ground
  const combination = visualCombinationBlock(option, selected, sex)
  if (combination) return combination
  return visualSplitBlock(option, splits)
}

function mutationsForSpecies(visualMutations, speciesId) {
  if (!speciesId) return []
  return visualMutations.filter((mutation) => String(mutation.species_id) === String(speciesId))
}

function mutationIdsFromRecord(record) {
  if (Array.isArray(record?.visual_mutation_ids) && record.visual_mutation_ids.length) {
    return record.visual_mutation_ids.filter((id) => id !== null && id !== '')
  }
  if (Array.isArray(record?.visual_mutations) && record.visual_mutations.length) {
    return record.visual_mutations.map((mutation) => mutation.id)
  }
  if (record?.visual_mutation_id) return [record.visual_mutation_id]
  return []
}

function keepMutationIdsForSpecies(ids, visualMutations, speciesId) {
  const allowed = new Set(mutationsForSpecies(visualMutations, speciesId).map((mutation) => String(mutation.id)))
  return (ids || []).filter((id) => allowed.has(String(id)))
}

function colorForSelection(baseColors, speciesId, baseColorId) {
  return colorsForSpecies(baseColors, speciesId).find((color) => String(color.id) === String(baseColorId)) || null
}

function GrandparentFields({
  title,
  sex,
  value,
  onChange,
  speciesOptions,
  baseColors,
  visualMutations,
  splitGenes,
  errors = {},
}) {
  return (
    <fieldset className="bird-modal__grandparent">
      <legend className="bird-modal__grandparent-title">{title}</legend>
      <div className="bird-modal__grid bird-modal__grid--gp">
        <SearchableSelect
          readable
          label="Species"
          options={speciesOptions}
          value={value.species_id}
          onChange={(next) => {
            const color = colorForSpeciesDefault(baseColors, next, value.base_color_id)
            const mutationIds = keepApplicableMutationIds(
              keepMutationIdsForSpecies(value.visual_mutation_ids, visualMutations, next),
              mutationsForSpecies(visualMutations, next),
              color,
            )
            const selectedVisuals = selectedMutationsFor(visualMutations, next, mutationIds)
            onChange({
              ...value,
              species_id: next,
              base_color_id: color?.id ?? null,
              visual_mutation_ids: mutationIds,
              visual_mutation_id: mutationIds[0] ?? null,
              split_gene_ids: keepGeneIds(value.split_gene_ids, splitGenes, next, sex, color, selectedVisuals),
              split_gene_id: null,
            })
          }}
          getOptionLabel={speciesLabel}
          getOptionValue={(option) => option.id}
          getOptionPreview={(option) => getSpeciesFormPreview(option, null)}
          allowEmpty
          emptyLabel="None"
          searchPlaceholder="Search species…"
          error={errors.species_id}
        />
        <SearchableSelect
          readable
          label="Base Color"
          options={colorsForSpecies(baseColors, value.species_id)}
          value={value.base_color_id}
          onChange={(next) => {
            const color = colorForSelection(baseColors, value.species_id, next)
            const mutations = mutationsForSpecies(visualMutations, value.species_id)
            const mutationIds = keepApplicableMutationIds(value.visual_mutation_ids, mutations, color)
            const selectedVisuals = selectedMutationsFor(visualMutations, value.species_id, mutationIds)
            onChange({
              ...value,
              base_color_id: next,
              visual_mutation_ids: mutationIds,
              visual_mutation_id: mutationIds[0] ?? null,
              split_gene_ids: keepGeneIds(
                value.split_gene_ids,
                splitGenes,
                value.species_id,
                sex,
                color,
                selectedVisuals,
              ),
              split_gene_id: null,
            })
          }}
          isOptionDisabled={(option) =>
            baseColorBlock(option, selectedGenesFor(splitGenes, value.species_id, sex, value.split_gene_ids))
          }
          allowEmpty
          emptyLabel="None"
          searchPlaceholder="Search base colors…"
          error={errors.base_color_id}
        />
        <SearchableSelect
          readable
          multiple
          label="Visual Mutation"
          options={mutationsForSpecies(visualMutations, value.species_id)}
          value={value.visual_mutation_ids || []}
          onChange={(next) => {
            const color = colorForSelection(baseColors, value.species_id, value.base_color_id)
            const selectedVisuals = selectedMutationsFor(visualMutations, value.species_id, next)
            onChange({
              ...value,
              visual_mutation_ids: next,
              visual_mutation_id: next[0] ?? null,
              split_gene_ids: keepGeneIds(
                value.split_gene_ids,
                splitGenes,
                value.species_id,
                sex,
                color,
                selectedVisuals,
              ),
              split_gene_id: null,
            })
          }}
          isOptionDisabled={(option) =>
            visualMutationBlock(
              option,
              selectedMutationsFor(visualMutations, value.species_id, value.visual_mutation_ids),
              colorForSelection(baseColors, value.species_id, value.base_color_id),
              sex,
              selectedGenesFor(splitGenes, value.species_id, sex, value.split_gene_ids),
            )
          }
          allowEmpty
          emptyLabel="None"
          disabled={!value.species_id}
          searchPlaceholder={value.species_id ? 'Search mutations…' : 'Select a species first'}
          error={errors.visual_mutation_ids || errors.visual_mutation_id}
        />
        <SearchableSelect
          readable
          multiple
          label="Split Genes / Hidden Genes"
          options={genesForBird(splitGenes, value.species_id, sex)}
          value={value.split_gene_ids || []}
          onChange={(next) =>
            onChange({
              ...value,
              split_gene_ids: next,
              split_gene_id: next[0] ?? null,
            })
          }
          isOptionDisabled={(option) =>
            splitGeneBlock(
              option,
              selectedGenesFor(splitGenes, value.species_id, sex, value.split_gene_ids),
              {
                sex,
                baseColor: colorForSelection(baseColors, value.species_id, value.base_color_id),
                visualMutations: selectedMutationsFor(
                  visualMutations,
                  value.species_id,
                  value.visual_mutation_ids,
                ),
              },
            )
          }
          allowEmpty
          emptyLabel="None"
          disabled={!value.species_id}
          searchPlaceholder={value.species_id ? 'Search genes…' : 'Select a species first'}
          error={errors.split_gene_ids || errors.split_gene_id}
        />
      </div>
    </fieldset>
  )
}

export default function BirdFormModal({
  open,
  mode = 'create',
  initialBird = null,
  speciesOptions = [],
  baseColors = [],
  visualMutations = [],
  splitGenes = [],
  submitting = false,
  onClose,
  onSubmit,
}) {
  const titleId = useId()
  const [form, setForm] = useState(createEmptyBirdForm)
  const [grandparentsOpen, setGrandparentsOpen] = useState(false)
  const [errors, setErrors] = useState({})
  const [formError, setFormError] = useState('')

  useEffect(() => {
    if (!open) return
    setForm(birdToForm(initialBird))
    setGrandparentsOpen(
      Boolean(
        initialBird &&
          GRANDPARENT_DEFS.some((item) => {
            const gp = initialBird.grandparents?.[item.key]
            return (
              gp &&
              (gp.species_id ||
                gp.base_color_id ||
                gp.visual_mutation_id ||
                gp.visual_mutation_ids?.length ||
                gp.split_gene_id ||
                gp.split_gene_ids?.length)
            )
          }),
      ),
    )
    setErrors({})
    setFormError('')
  }, [open, initialBird])

  useEffect(() => {
    if (!open) return undefined
    const previous = document.body.style.overflow
    document.body.style.overflow = 'hidden'

    const onKeyDown = (event) => {
      if (event.key === 'Escape') {
        if (document.querySelector('.search-select__menu')) return
        onClose()
      }
    }

    document.addEventListener('keydown', onKeyDown)
    return () => {
      document.body.style.overflow = previous
      document.removeEventListener('keydown', onKeyDown)
    }
  }, [open, onClose])

  if (!open) return null

  const updateField = (field, value) => {
    setForm((prev) => ({ ...prev, [field]: value }))
  }

  const updateGrandparent = (role, next) => {
    setForm((prev) => ({
      ...prev,
      grandparents: {
        ...prev.grandparents,
        [role]: next,
      },
    }))
  }

  const handleSubmit = async (event) => {
    event.preventDefault()
    setErrors({})
    setFormError('')

    const localErrors = {}
    if (!String(form.bird_id).trim()) localErrors.bird_id = ['Bird ID is required.']
    if (form.age_months === '' || form.age_months === null) localErrors.age_months = ['Age is required.']
    if (!form.species_id) localErrors.species_id = ['Species is required.']
    if (!form.sex) localErrors.sex = ['Sex is required.']

    if (Object.keys(localErrors).length) {
      setErrors(localErrors)
      setFormError('Please complete the required bird fields.')
      return
    }

    const payload = {
      bird_id: String(form.bird_id).trim(),
      age_months: Number(form.age_months),
      species_id: form.species_id,
      sex: form.sex,
      base_color_id: form.base_color_id || null,
      visual_mutation_id: form.visual_mutation_ids?.[0] || null,
      visual_mutation_ids: form.visual_mutation_ids || [],
      split_gene_id: form.split_gene_ids?.[0] || null,
      split_gene_ids: form.split_gene_ids || [],
      grandparents: Object.fromEntries(
        GRANDPARENT_DEFS.map(({ key }) => {
          const gp = form.grandparents[key]
          const mutationIds = keepMutationIdsForSpecies(gp.visual_mutation_ids, visualMutations, gp.species_id)
          const geneIds = keepGeneIds(
            gp.split_gene_ids,
            splitGenes,
            gp.species_id,
            sexForGrandparent(key),
            colorForSelection(baseColors, gp.species_id, gp.base_color_id),
            selectedMutationsFor(visualMutations, gp.species_id, mutationIds),
          )
          return [
            key,
            {
              ...gp,
              visual_mutation_ids: mutationIds,
              visual_mutation_id: mutationIds[0] ?? null,
              split_gene_ids: geneIds,
              split_gene_id: geneIds[0] ?? null,
            },
          ]
        }),
      ),
    }

    try {
      await onSubmit(payload)
    } catch (error) {
      const responseErrors = error?.response?.data?.errors
      const message = error?.response?.data?.message
      if (responseErrors) setErrors(responseErrors)
      setFormError(message || 'Unable to save bird. Please review the form and try again.')
    }
  }

  const firstError = (key) => {
    const value = errors?.[key]
    if (Array.isArray(value)) return value[0]
    if (typeof value === 'string') return value
    return ''
  }

  return (
    <div className="bird-modal" role="presentation">
      <button type="button" className="bird-modal__backdrop" aria-label="Close dialog" onClick={onClose} />

      <div
        className="bird-modal__dialog"
        role="dialog"
        aria-modal="true"
        aria-labelledby={titleId}
      >
        <header className="bird-modal__header">
          <div>
            <p className="bird-modal__eyebrow">Birds Management</p>
            <h2 id={titleId} className="bird-modal__title">
              {mode === 'edit' ? 'Edit Bird' : 'Add Bird'}
            </h2>
          </div>
          <button type="button" className="bird-modal__close" aria-label="Close" onClick={onClose}>
            ×
          </button>
        </header>

        <form className="bird-modal__form" onSubmit={handleSubmit} noValidate>
          <div className="bird-modal__body">
            {formError ? <p className="bird-modal__banner">{formError}</p> : null}

            <section className="bird-modal__section">
              <h3 className="bird-modal__section-title">Main Bird Information</h3>
              <div className="bird-modal__grid">
                <label className="field">
                  <span className="field__label">
                    Bird ID <span className="search-select__required">*</span>
                  </span>
                  <input
                    className={`field__input${firstError('bird_id') ? ' is-invalid' : ''}`}
                    type="text"
                    value={form.bird_id}
                    onChange={(event) => updateField('bird_id', event.target.value)}
                    placeholder="e.g. LB-2044"
                    autoComplete="off"
                  />
                  {firstError('bird_id') ? <span className="field__error">{firstError('bird_id')}</span> : null}
                </label>

                <label className="field">
                  <span className="field__label">
                    Age (Months) <span className="search-select__required">*</span>
                  </span>
                  <select
                    className={`field__input${firstError('age_months') ? ' is-invalid' : ''}`}
                    value={form.age_months === '' || form.age_months === null ? '' : String(form.age_months)}
                    onChange={(event) => updateField('age_months', event.target.value)}
                  >
                    <option value="">Select age</option>
                    {ageMonthOptions(form.age_months).map((months) => (
                      <option key={months} value={String(months)}>
                        {months} {months === 1 ? 'month' : 'months'}
                      </option>
                    ))}
                  </select>
                  {firstError('age_months') ? (
                    <span className="field__error">{firstError('age_months')}</span>
                  ) : null}
                </label>

                <SearchableSelect
                  readable
                  label="Species"
                  required
                  options={speciesOptions}
                  value={form.species_id}
                  onChange={(next) => {
                    const color = colorForSpeciesDefault(baseColors, next, form.base_color_id)
                    const speciesMutations = mutationsForSpecies(visualMutations, next)
                    const mutationIds = keepApplicableMutationIds(
                      keepMutationIdsForSpecies(form.visual_mutation_ids, visualMutations, next),
                      speciesMutations,
                      color,
                    )
                    const geneIds = keepGeneIds(
                      form.split_gene_ids,
                      splitGenes,
                      next,
                      form.sex,
                      color,
                      selectedMutationsFor(visualMutations, next, mutationIds),
                    )
                    setForm((prev) => ({
                      ...prev,
                      species_id: next,
                      base_color_id: color?.id ?? null,
                      visual_mutation_ids: mutationIds,
                      visual_mutation_id: mutationIds[0] ?? null,
                      split_gene_ids: geneIds,
                      split_gene_id: geneIds[0] ?? null,
                    }))
                  }}
                  getOptionLabel={speciesLabel}
                  getOptionValue={(option) => option.id}
                  getOptionPreview={(option) => getSpeciesFormPreview(option, form.sex)}
                  allowEmpty={false}
                  placeholder="Select species"
                  emptyLabel="Select species"
                  searchPlaceholder="Search species…"
                  error={firstError('species_id')}
                />

                <label className="field">
                  <span className="field__label">
                    Sex <span className="search-select__required">*</span>
                  </span>
                  <select
                    className={`field__input${firstError('sex') ? ' is-invalid' : ''}`}
                    value={form.sex}
                    onChange={(event) => {
                      const nextSex = event.target.value
                      const mutations = mutationsForSpecies(visualMutations, form.species_id)
                      const mutationIds = keepCombinableMutationIds(form.visual_mutation_ids, mutations, nextSex)
                      const color = colorForSelection(baseColors, form.species_id, form.base_color_id)
                      const geneIds = keepGeneIds(
                        form.split_gene_ids,
                        splitGenes,
                        form.species_id,
                        nextSex,
                        color,
                        selectedMutationsFor(visualMutations, form.species_id, mutationIds),
                      )
                      setForm((prev) => ({
                        ...prev,
                        sex: nextSex,
                        split_gene_ids: geneIds,
                        split_gene_id: geneIds[0] ?? null,
                        visual_mutation_ids: mutationIds,
                        visual_mutation_id: mutationIds[0] ?? null,
                      }))
                    }}
                  >
                    <option value="">Select sex</option>
                    {SEX_OPTIONS.map((option) => (
                      <option key={option.value} value={option.value}>
                        {option.label}
                      </option>
                    ))}
                  </select>
                  {firstError('sex') ? <span className="field__error">{firstError('sex')}</span> : null}
                </label>

                <SearchableSelect
                  readable
                  label="Base Color"
                  options={colorsForSpecies(baseColors, form.species_id)}
                  value={form.base_color_id}
                  onChange={(next) => {
                    const color = colorForSelection(baseColors, form.species_id, next)
                    const mutations = mutationsForSpecies(visualMutations, form.species_id)
                    const mutationIds = keepApplicableMutationIds(form.visual_mutation_ids, mutations, color)
                    const geneIds = keepGeneIds(
                      form.split_gene_ids,
                      splitGenes,
                      form.species_id,
                      form.sex,
                      color,
                      selectedMutationsFor(visualMutations, form.species_id, mutationIds),
                    )
                    setForm((prev) => ({
                      ...prev,
                      base_color_id: next,
                      visual_mutation_ids: mutationIds,
                      visual_mutation_id: mutationIds[0] ?? null,
                      split_gene_ids: geneIds,
                      split_gene_id: geneIds[0] ?? null,
                    }))
                  }}
                  isOptionDisabled={(option) =>
                    baseColorBlock(
                      option,
                      selectedGenesFor(splitGenes, form.species_id, form.sex, form.split_gene_ids),
                    )
                  }
                  allowEmpty
                  emptyLabel="None"
                  searchPlaceholder="Search base colors…"
                  error={firstError('base_color_id')}
                />

                <SearchableSelect
                  readable
                  multiple
                  label="Visual Mutation"
                  options={mutationsForSpecies(visualMutations, form.species_id)}
                  value={form.visual_mutation_ids}
                  onChange={(next) => {
                    const color = colorForSelection(baseColors, form.species_id, form.base_color_id)
                    const geneIds = keepGeneIds(
                      form.split_gene_ids,
                      splitGenes,
                      form.species_id,
                      form.sex,
                      color,
                      selectedMutationsFor(visualMutations, form.species_id, next),
                    )
                    setForm((prev) => ({
                      ...prev,
                      visual_mutation_ids: next,
                      visual_mutation_id: next[0] ?? null,
                      split_gene_ids: geneIds,
                      split_gene_id: geneIds[0] ?? null,
                    }))
                  }}
                  isOptionDisabled={(option) =>
                    visualMutationBlock(
                      option,
                      selectedMutationsFor(visualMutations, form.species_id, form.visual_mutation_ids),
                      colorForSelection(baseColors, form.species_id, form.base_color_id),
                      form.sex,
                      selectedGenesFor(splitGenes, form.species_id, form.sex, form.split_gene_ids),
                    )
                  }
                  allowEmpty
                  emptyLabel="None"
                  disabled={!form.species_id}
                  searchPlaceholder={form.species_id ? 'Search mutations…' : 'Select a species first'}
                  error={firstError('visual_mutation_ids') || firstError('visual_mutation_id')}
                />

                <div className="bird-modal__span-2">
                  <SearchableSelect
                    readable
                    multiple
                    label="Split Genes / Hidden Genes"
                    options={genesForBird(splitGenes, form.species_id, form.sex)}
                    value={form.split_gene_ids}
                    onChange={(next) =>
                      setForm((prev) => ({
                        ...prev,
                        split_gene_ids: next,
                        split_gene_id: next[0] ?? null,
                      }))
                    }
                    isOptionDisabled={(option) =>
                      splitGeneBlock(
                        option,
                        selectedGenesFor(splitGenes, form.species_id, form.sex, form.split_gene_ids),
                        {
                          sex: form.sex,
                          baseColor: colorForSelection(baseColors, form.species_id, form.base_color_id),
                          visualMutations: selectedMutationsFor(
                            visualMutations,
                            form.species_id,
                            form.visual_mutation_ids,
                          ),
                        },
                      )
                    }
                    allowEmpty
                    emptyLabel="None"
                    disabled={!form.species_id}
                    searchPlaceholder={
                      form.species_id ? 'Search genes…' : 'Select a species first'
                    }
                    error={firstError('split_gene_ids') || firstError('split_gene_id')}
                  />
                </div>
              </div>
            </section>

            <section className="bird-modal__grandparents">
              <button
                type="button"
                className="bird-modal__gp-toggle"
                aria-expanded={grandparentsOpen}
                onClick={() => setGrandparentsOpen((openState) => !openState)}
              >
                <span>
                  <span className="bird-modal__gp-title">Grandparents Information</span>
                  <span className="bird-modal__gp-hint">Optional lineage fields</span>
                </span>
                <span className={`bird-modal__gp-chevron${grandparentsOpen ? ' is-open' : ''}`} aria-hidden="true" />
              </button>

              {grandparentsOpen ? (
                <div className="bird-modal__gp-body">
                  {GRANDPARENT_DEFS.map((item) => (
                    <GrandparentFields
                      key={item.key}
                      title={item.title}
                      sex={sexForGrandparent(item.key)}
                      value={form.grandparents[item.key]}
                      onChange={(next) => updateGrandparent(item.key, next)}
                      speciesOptions={speciesOptions}
                      baseColors={baseColors}
                      visualMutations={visualMutations}
                      splitGenes={splitGenes}
                      errors={{
                        species_id: firstError(`grandparents.${item.key}.species_id`),
                        base_color_id: firstError(`grandparents.${item.key}.base_color_id`),
                        visual_mutation_id: firstError(`grandparents.${item.key}.visual_mutation_id`),
                        visual_mutation_ids: firstError(`grandparents.${item.key}.visual_mutation_ids`),
                        split_gene_id: firstError(`grandparents.${item.key}.split_gene_id`),
                        split_gene_ids: firstError(`grandparents.${item.key}.split_gene_ids`),
                      }}
                    />
                  ))}
                </div>
              ) : null}
            </section>
          </div>

          <footer className="bird-modal__footer">
            <button type="button" className="birds__btn birds__btn--ghost" onClick={onClose} disabled={submitting}>
              Cancel
            </button>
            <button type="submit" className="birds__btn birds__btn--primary" disabled={submitting}>
              {submitting ? 'Saving…' : mode === 'edit' ? 'Update Bird' : 'Save Bird'}
            </button>
          </footer>
        </form>
      </div>
    </div>
  )
}
