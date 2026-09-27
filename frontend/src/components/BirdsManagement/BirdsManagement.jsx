import { useCallback, useEffect, useId, useMemo, useRef, useState } from 'react'
import { createPortal } from 'react-dom'
import api from '../../api/client'
import { getBirds, getCatalog } from '../../api/catalogs'
import { getSpeciesFormPreview } from '../../assets/species-form/index.js'
import BirdFormModal from './BirdFormModal.jsx'
import './BirdsManagement.css'

function sexDisplay(bird) {
  if (bird.sex_label) return bird.sex_label
  if (bird.sex === 'hen') return 'Hen — Female'
  if (bird.sex === 'cock') return 'Cock — Male'
  return bird.sex || '—'
}

function speciesDisplay(bird) {
  return bird.species?.label || bird.species?.common_name || '—'
}

function geneDisplay(bird) {
  const names = (bird.split_genes || []).map((gene) => gene?.name).filter(Boolean)
  if (names.length) return names.join(', ')
  return bird.split_gene?.name || '—'
}

function visualMutationDisplay(record) {
  const names = (record?.visual_mutations || []).map((mutation) => mutation?.name).filter(Boolean)
  if (names.length) return names.join(', ')
  return record?.visual_mutation?.name || '—'
}

const GRANDPARENT_LABELS = [
  ['paternal_grandfather', 'Paternal Grandfather'],
  ['paternal_grandmother', 'Paternal Grandmother'],
  ['maternal_grandfather', 'Maternal Grandfather'],
  ['maternal_grandmother', 'Maternal Grandmother'],
]

function grandparentSummary(record) {
  if (!record) return null
  const parts = [
    record.species?.label || record.species?.common_name,
    record.base_color?.name,
    visualMutationDisplay(record) === '—' ? null : visualMutationDisplay(record),
    geneDisplay(record) === '—' ? null : geneDisplay(record),
  ].filter(Boolean)
  return parts.length ? parts.join(' · ') : null
}

function sexLabel(sex) {
  if (sex === 'hen') return 'Hen — Female'
  if (sex === 'cock') return 'Cock — Male'
  return sex || 'None'
}

function speciesOptionLabel(option) {
  if (!option) return 'None'
  if (option.alternate_names) return `${option.common_name} (${option.alternate_names})`
  return option.label || option.common_name || option.name || 'None'
}

function catalogLabel(list, id) {
  if (id === null || id === undefined || id === '') return 'None'
  const row = (list || []).find((item) => String(item.id) === String(id))
  return row?.label || row?.name || row?.common_name || 'None'
}

function idListLabel(list, ids) {
  const names = (ids || [])
    .map((id) => catalogLabel(list, id))
    .filter((name) => name && name !== 'None')
  return names.length ? names.join(', ') : 'None'
}

function payloadGrandparentLine(record, catalogs) {
  if (!record?.species_id) return 'None'
  const parts = [
    catalogLabel(catalogs.speciesOptions, record.species_id),
    catalogLabel(catalogs.baseColors, record.base_color_id),
    idListLabel(catalogs.visualMutations, record.visual_mutation_ids),
    idListLabel(catalogs.splitGenes, record.split_gene_ids),
  ].filter((part) => part && part !== 'None')
  return parts.length ? parts.join(' · ') : 'None'
}

function editHistory(bird, payload, catalogs) {
  const changes = []
  const push = (label, before, after) => {
    if (String(before) !== String(after)) changes.push({ label, before, after })
  }

  push('Bird ID', bird.bird_id || 'None', payload.bird_id || 'None')
  push(
    'Species',
    speciesOptionLabel(bird.species) === 'None' ? speciesDisplay(bird) : speciesOptionLabel(bird.species),
    speciesOptionLabel((catalogs.speciesOptions || []).find((item) => String(item.id) === String(payload.species_id))),
  )
  push('Sex', sexDisplay(bird), sexLabel(payload.sex))
  push('Age', bird.age_months == null ? 'None' : `${bird.age_months} mo`, `${payload.age_months} mo`)
  push('Base Color', bird.base_color?.name || 'None', catalogLabel(catalogs.baseColors, payload.base_color_id))
  push('Visual Mutation', visualMutationDisplay(bird) === '—' ? 'None' : visualMutationDisplay(bird), idListLabel(catalogs.visualMutations, payload.visual_mutation_ids))
  push('Split / Hidden Genes', geneDisplay(bird) === '—' ? 'None' : geneDisplay(bird), idListLabel(catalogs.splitGenes, payload.split_gene_ids))

  GRANDPARENT_LABELS.forEach(([key, label]) => {
    push(
      label,
      grandparentSummary(bird.grandparents?.[key]) || 'None',
      payloadGrandparentLine(payload.grandparents?.[key], catalogs),
    )
  })

  return changes
}

function CenterConfirm({ title, copy, children, yesLabel, noLabel, onYes, onNo, busy }) {
  useEffect(() => {
    const onKeyDown = (event) => {
      if (event.key === 'Escape' && !busy) onNo()
    }
    window.addEventListener('keydown', onKeyDown)
    return () => window.removeEventListener('keydown', onKeyDown)
  }, [busy, onNo])

  return createPortal(
    <div className="bird-confirm">
      <button type="button" className="bird-confirm__backdrop" aria-label="No" onClick={onNo} disabled={busy} />
      <div className="bird-confirm__dialog" role="dialog" aria-modal="true" aria-labelledby="bird-confirm-title">
        <h2 id="bird-confirm-title" className="bird-confirm__title">{title}</h2>
        {copy ? <p className="bird-confirm__copy">{copy}</p> : null}
        {children}
        <div className="bird-confirm__actions">
          {noLabel ? (
            <button type="button" className="birds__btn birds__btn--ghost" onClick={onNo} disabled={busy}>
              {noLabel}
            </button>
          ) : null}
          <button type="button" className="birds__btn birds__btn--primary" onClick={onYes} disabled={busy}>
            {busy ? 'Please wait…' : yesLabel}
          </button>
        </div>
      </div>
    </div>,
    document.body,
  )
}

function EditHistoryList({ changes }) {
  if (!changes.length) {
    return <p className="bird-confirm__copy">No stored fields changed.</p>
  }

  return (
    <ul className="bird-confirm__history">
      {changes.map((change) => (
        <li key={change.label}>
          <strong>{change.label}</strong>
          <span>{change.before}</span>
          <span aria-hidden="true">→</span>
          <span>{change.after}</span>
        </li>
      ))}
    </ul>
  )
}

function birdPreview(bird) {
  return getSpeciesFormPreview(
    {
      id: bird.species_id || bird.species?.id,
      common_name: bird.species?.common_name,
    },
    bird.sex,
  )
}

function placeHoverCard(rect) {
  const width = Math.min(380, window.innerWidth - 16)
  const height = 320
  const gap = 10
  const pad = 8
  let left = rect.right + gap
  let top = rect.top

  if (left + width > window.innerWidth - pad) {
    left = rect.left - gap - width
  }
  if (left < pad) {
    left = Math.max(pad, (window.innerWidth - width) / 2)
    top = rect.bottom + gap
  }
  if (top + height > window.innerHeight - pad) {
    top = Math.max(pad, window.innerHeight - height - pad)
  }
  if (top < pad) top = pad

  return {
    top: `${Math.round(top)}px`,
    left: `${Math.round(left)}px`,
    width: `${Math.round(width)}px`,
  }
}

function BirdHoverCard({ bird, anchor }) {
  const grandparents = GRANDPARENT_LABELS.map(([key, label]) => ({
    label,
    value: grandparentSummary(bird.grandparents?.[key]),
  })).filter((item) => item.value)
  const preview = birdPreview(bird)

  return createPortal(
    <div className="bird-hover-card" style={placeHoverCard(anchor)} role="tooltip">
      <p className="bird-hover-card__role">Bird profile</p>
      {preview?.src ? (
        <div className="bird-hover-card__figure">
          <img src={preview.src} alt="" />
        </div>
      ) : null}
      <div className="bird-hover-card__body">
        <p className="bird-hover-card__id">{bird.bird_id}</p>
        <dl>
          <div>
            <dt>Species</dt>
            <dd>{speciesDisplay(bird)}</dd>
          </div>
          <div>
            <dt>Sex</dt>
            <dd>{sexDisplay(bird)}</dd>
          </div>
          <div>
            <dt>Age</dt>
            <dd>{bird.age_months} mo</dd>
          </div>
          <div>
            <dt>Base Color</dt>
            <dd>{bird.base_color?.name || '—'}</dd>
          </div>
          <div>
            <dt>Visual Mutation</dt>
            <dd>{visualMutationDisplay(bird) || '—'}</dd>
          </div>
          <div>
            <dt>Split / Hidden Genes</dt>
            <dd>{geneDisplay(bird)}</dd>
          </div>
        </dl>
      </div>
      {grandparents.length ? (
        <div className="bird-hover-card__lineage">
          {grandparents.map((item) => (
            <p key={item.label}>
              <strong>{item.label}</strong>
              <span>{item.value}</span>
            </p>
          ))}
        </div>
      ) : null}
    </div>,
    document.body,
  )
}

function BirdDetails({ bird, onClose }) {
  const grandparents = GRANDPARENT_LABELS.map(([key, label]) => ({
    label,
    value: grandparentSummary(bird.grandparents?.[key]),
  })).filter((item) => item.value)
  const preview = onClose
    ? getSpeciesFormPreview(
        {
          id: bird.species_id || bird.species?.id,
          common_name: bird.species?.common_name,
        },
        bird.sex,
      )
    : null

  return (
    <div className="bird-details">
      <div className="bird-details__head">
        <p className="bird-details__title" id={onClose ? 'bird-details-title' : undefined}>
          {bird.bird_id}
        </p>
        {onClose ? (
          <button type="button" className="bird-details__close" onClick={onClose}>
            Close
          </button>
        ) : null}
      </div>
      <div className="bird-details__layout">
        {preview?.src ? (
          <div className="bird-details__figure">
            <img src={preview.src} alt={preview.alt || speciesDisplay(bird)} />
          </div>
        ) : null}
        <div className="bird-details__content">
      <dl className="bird-details__list">
        <div>
          <dt>Bird ID</dt>
          <dd>{bird.bird_id}</dd>
        </div>
        <div>
          <dt>Species</dt>
          <dd>{speciesDisplay(bird)}</dd>
        </div>
        <div>
          <dt>Sex</dt>
          <dd>{sexDisplay(bird)}</dd>
        </div>
        <div>
          <dt>Age</dt>
          <dd>{bird.age_months} mo</dd>
        </div>
        <div>
          <dt>Base Color</dt>
          <dd>{bird.base_color?.name || '—'}</dd>
        </div>
        <div>
          <dt>Visual Mutation</dt>
          <dd>{visualMutationDisplay(bird) || '—'}</dd>
        </div>
        <div>
          <dt>Split / Hidden Genes</dt>
          <dd>{geneDisplay(bird)}</dd>
        </div>
      </dl>
      {grandparents.length ? (
        <div className="bird-details__lineage">
          <p className="bird-details__lineage-title">Grandparents Information</p>
          <ul>
            {grandparents.map((item) => (
              <li key={item.label}>
                <strong>{item.label}</strong>
                <span>{item.value}</span>
              </li>
            ))}
          </ul>
        </div>
      ) : (
        <p className="bird-details__empty">No grandparents information saved.</p>
      )}
        </div>
      </div>
    </div>
  )
}

export default function BirdsManagement() {
  const listId = useId()
  const [birds, setBirds] = useState([])
  const [speciesOptions, setSpeciesOptions] = useState([])
  const [baseColors, setBaseColors] = useState([])
  const [visualMutations, setVisualMutations] = useState([])
  const [splitGenes, setSplitGenes] = useState([])
  const [search, setSearch] = useState('')
  const [speciesFilter, setSpeciesFilter] = useState('')
  const [sexFilter, setSexFilter] = useState('')
  const [viewMode, setViewMode] = useState('cards')
  const [loading, setLoading] = useState(true)
  const [loadError, setLoadError] = useState('')
  const [modalOpen, setModalOpen] = useState(false)
  const [modalMode, setModalMode] = useState('create')
  const [editingBird, setEditingBird] = useState(null)
  const [submitting, setSubmitting] = useState(false)
  const [actionError, setActionError] = useState('')
  const [hoveredBirdId, setHoveredBirdId] = useState(null)
  const [hoverAnchor, setHoverAnchor] = useState(null)
  const [selectedBirdId, setSelectedBirdId] = useState(null)
  const [deleteTarget, setDeleteTarget] = useState(null)
  const [editReview, setEditReview] = useState(null)
  const [savedEdit, setSavedEdit] = useState(null)

  const catalogsLoadedRef = useRef(false)

  const loadBirds = useCallback(async () => {
    const response = await getBirds({ fresh: true })
    setBirds(response.data?.data ?? [])
  }, [])

  const loadCatalogs = useCallback(async () => {
    if (catalogsLoadedRef.current) return
    const results = await Promise.allSettled([
      getCatalog('/base-colors'),
      getCatalog('/visual-mutations'),
      getCatalog('/split-genes'),
    ])
    const [colorsRes, mutationsRes, genesRes] = results
    if (colorsRes.status === 'fulfilled') setBaseColors(colorsRes.value.data?.data ?? [])
    if (mutationsRes.status === 'fulfilled') setVisualMutations(mutationsRes.value.data?.data ?? [])
    if (genesRes.status === 'fulfilled') setSplitGenes(genesRes.value.data?.data ?? [])
    catalogsLoadedRef.current =
      colorsRes.status === 'fulfilled' &&
      mutationsRes.status === 'fulfilled' &&
      genesRes.status === 'fulfilled'
  }, [])

  const loadPageData = useCallback(async () => {
    setLoading(true)
    setLoadError('')

    const results = await Promise.allSettled([
      getCatalog('/lovebird-species'),
      getBirds(),
    ])
    const [speciesRes, birdsRes] = results

    if (speciesRes.status === 'fulfilled') setSpeciesOptions(speciesRes.value.data?.data ?? [])
    if (birdsRes.status === 'fulfilled') setBirds(birdsRes.value.data?.data ?? [])

    if (birdsRes.status === 'rejected') {
      setLoadError('Unable to load saved birds. Make sure php artisan serve is running, then retry.')
    } else if (speciesRes.status === 'rejected') {
      setLoadError('Birds loaded, but species options did not. Retry before adding a new record.')
    }

    setLoading(false)
    loadCatalogs()
  }, [loadCatalogs])

  useEffect(() => {
    loadPageData()
  }, [loadPageData])

  const selectedBird = useMemo(
    () =>
      birds.find((bird) => bird.id === selectedBirdId) ||
      null,
    [birds, selectedBirdId],
  )

  useEffect(() => {
    if (!selectedBird) return undefined
    const previous = document.body.style.overflow
    document.body.style.overflow = 'hidden'

    const onKeyDown = (event) => {
      if (event.key === 'Escape') setSelectedBirdId(null)
    }

    document.addEventListener('keydown', onKeyDown)
    return () => {
      document.body.style.overflow = previous
      document.removeEventListener('keydown', onKeyDown)
    }
  }, [selectedBird])

  const filteredBirds = useMemo(() => {
    const query = search.trim().toLowerCase()

    return birds.filter((bird) => {
      if (speciesFilter && String(bird.species_id) !== speciesFilter) return false
      if (sexFilter && bird.sex !== sexFilter) return false
      if (!query) return true

      return [
        bird.bird_id,
        speciesDisplay(bird),
        sexDisplay(bird),
        bird.base_color?.name,
        visualMutationDisplay(bird),
        geneDisplay(bird),
        String(bird.age_months),
      ]
        .filter(Boolean)
        .join(' ')
        .toLowerCase()
        .includes(query)
    })
  }, [birds, search, speciesFilter, sexFilter])

  const openCreateModal = () => {
    setModalMode('create')
    setEditingBird(null)
    setActionError('')
    setModalOpen(true)
    loadCatalogs()
  }

  const openEditModal = (bird) => {
    setModalMode('edit')
    setEditingBird(bird)
    setActionError('')
    setModalOpen(true)
    loadCatalogs()
  }

  const closeModal = () => {
    if (submitting) return
    setModalOpen(false)
    setEditingBird(null)
    setEditReview(null)
  }

  const saveBird = async (payload) => {
    setSubmitting(true)
    setActionError('')
    try {
      if (modalMode === 'edit' && editingBird?.id) {
        await api.put(`/birds/${editingBird.id}`, payload)
      } else {
        await api.post('/birds', payload)
      }
      await loadBirds()
      setModalOpen(false)
      setEditingBird(null)
    } finally {
      setSubmitting(false)
    }
  }

  const handleSubmit = async (payload) => {
    if (modalMode === 'edit' && editingBird?.id) {
      setEditReview({
        payload,
        birdId: editingBird.bird_id,
        changes: editHistory(editingBird, payload, {
          speciesOptions,
          baseColors,
          visualMutations,
          splitGenes,
        }),
      })
      return
    }

    await saveBird(payload)
  }

  const confirmEdit = async () => {
    if (!editReview) return
    const review = editReview
    try {
      await saveBird(review.payload)
      setEditReview(null)
      setSavedEdit(review)
    } catch (error) {
      const message = error?.response?.data?.message || 'Unable to save this edit.'
      setEditReview((current) => (current ? { ...current, error: message } : current))
    }
  }

  const confirmDelete = async () => {
    if (!deleteTarget) return
    setSubmitting(true)
    setActionError('')
    try {
      await api.delete(`/birds/${deleteTarget.id}`)
      if (selectedBirdId === deleteTarget.id) setSelectedBirdId(null)
      setDeleteTarget(null)
      await loadBirds()
    } catch {
      setActionError(`Unable to delete ${deleteTarget.bird_id}.`)
      setDeleteTarget(null)
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <main id="birds" className="birds">
      <div className="birds__shell">
        <header className="birds__header">
          <div className="birds__header-copy">
            <p className="birds__eyebrow">Records</p>
            <h1 className="birds__title">Birds Management</h1>
            <p className="birds__lede">
              Store lovebird profiles for breeding. Add, edit, search, and delete records. Lineage
              details are optional.
            </p>
          </div>
          <button type="button" className="birds__btn birds__btn--primary birds__btn--add" onClick={openCreateModal}>
            Add Bird
          </button>
        </header>

        {loadError ? (
          <div className="birds__alert" role="alert">
            <p>{loadError}</p>
            <button type="button" className="birds__btn birds__btn--ghost" onClick={loadPageData}>
              Retry
            </button>
          </div>
        ) : null}
        {actionError ? <p className="birds__alert">{actionError}</p> : null}

        <section className="birds-panel birds-panel--list" aria-labelledby={`${listId}-list`}>
          <div className="birds-panel__head birds-panel__head--row">
            <div>
              <h2 id={`${listId}-list`} className="birds-panel__title">
                Saved Birds
              </h2>
              <p className="birds-panel__subtitle">
                {loading ? (
                  'Loading records…'
                ) : (
                  <>
                    <span className="birds-panel__count">{filteredBirds.length}</span>
                    {` record${filteredBirds.length === 1 ? '' : 's'} shown`}
                  </>
                )}
              </p>
            </div>

            <div className="birds-panel__actions">
              <div className="birds__view-toggle" role="group" aria-label="List view mode">
                <button
                  type="button"
                  className={`birds__view-btn${viewMode === 'cards' ? ' is-active' : ''}`}
                  onClick={() => {
                    setViewMode('cards')
                    setHoveredBirdId(null)
                    setHoverAnchor(null)
                  }}
                >
                  Cards
                </button>
                <button
                  type="button"
                  className={`birds__view-btn${viewMode === 'table' ? ' is-active' : ''}`}
                  onClick={() => {
                    setViewMode('table')
                    setHoveredBirdId(null)
                    setHoverAnchor(null)
                  }}
                >
                  Table
                </button>
              </div>
            </div>
          </div>

          <div className="birds-filters">
            <label className="birds__search birds-filters__field birds-filters__search">
              <span>Search</span>
              <span className="birds__search-control">
                <svg
                  className="birds__search-icon"
                  viewBox="0 0 24 24"
                  aria-hidden="true"
                  focusable="false"
                >
                  <circle cx="11" cy="11" r="6.5" fill="none" stroke="currentColor" strokeWidth="1.8" />
                  <path
                    d="M16.2 16.2 20 20"
                    fill="none"
                    stroke="currentColor"
                    strokeWidth="1.8"
                    strokeLinecap="round"
                  />
                </svg>
                <input
                  type="search"
                  value={search}
                  onChange={(event) => setSearch(event.target.value)}
                  placeholder="Search by ID, species, color…"
                  autoComplete="off"
                />
              </span>
            </label>

            <label className="birds-filters__field">
              <span>Species</span>
              <select
                value={speciesFilter}
                onChange={(event) => setSpeciesFilter(event.target.value)}
              >
                <option value="">All Species</option>
                {speciesOptions.map((option) => (
                  <option key={option.id} value={option.id}>
                    {option.alternate_names
                      ? `${option.common_name} (${option.alternate_names})`
                      : option.common_name}
                  </option>
                ))}
              </select>
            </label>

            <label className="birds-filters__field">
              <span>Sex</span>
              <select value={sexFilter} onChange={(event) => setSexFilter(event.target.value)}>
                <option value="">All Sex</option>
                <option value="cock">Cock (Male)</option>
                <option value="hen">Hen (Female)</option>
              </select>
            </label>

            {search || speciesFilter || sexFilter ? (
              <button
                type="button"
                className="birds__btn birds__btn--ghost birds-filters__clear"
                onClick={() => {
                  setSearch('')
                  setSpeciesFilter('')
                  setSexFilter('')
                }}
              >
                Clear filters
              </button>
            ) : null}
          </div>

          {loading ? (
            <div className="birds-empty">
              <p className="birds-empty__title">Loading birds</p>
              <p className="birds-empty__copy">Fetching saved records from the server.</p>
            </div>
          ) : filteredBirds.length === 0 ? (
            <div className="birds-empty">
              <p className="birds-empty__title">No birds yet</p>
              <p className="birds-empty__copy">
                {search.trim() || speciesFilter || sexFilter
                  ? 'No saved birds match the current search or filters.'
                  : 'Add a lovebird record to start planning pairings.'}
              </p>
              {search.trim() || speciesFilter || sexFilter ? (
                <button
                  type="button"
                  className="birds__btn birds__btn--ghost"
                  onClick={() => {
                    setSearch('')
                    setSpeciesFilter('')
                    setSexFilter('')
                  }}
                >
                  Clear filters
                </button>
              ) : (
                <button type="button" className="birds__btn birds__btn--primary" onClick={openCreateModal}>
                  Add Bird
                </button>
              )}
            </div>
          ) : viewMode === 'cards' ? (
            <>
              <ul className="bird-cards">
                {filteredBirds.map((bird) => {
                  const selected = selectedBirdId === bird.id
                  const showHover = hoveredBirdId === bird.id && hoverAnchor && !selectedBirdId
                  const preview = birdPreview(bird)
                  return (
                    <li
                      key={bird.id}
                      className={`bird-card${selected ? ' is-selected' : ''}`}
                      onMouseEnter={(event) => {
                        setHoveredBirdId(bird.id)
                        setHoverAnchor(event.currentTarget.getBoundingClientRect())
                      }}
                      onMouseLeave={() => {
                        setHoveredBirdId((current) => (current === bird.id ? null : current))
                        setHoverAnchor(null)
                      }}
                    >
                      <button
                        type="button"
                        className="bird-card__select"
                        aria-pressed={selected}
                        onClick={() => setSelectedBirdId((current) => (current === bird.id ? null : bird.id))}
                      >
                        {preview?.src ? (
                          <span className="bird-card__figure">
                            <img src={preview.src} alt="" />
                          </span>
                        ) : null}
                        <span className="bird-card__top">
                          <span className="bird-card__id">{bird.bird_id}</span>
                          <span className="bird-card__species">{speciesDisplay(bird)}</span>
                        </span>

                        <dl className="bird-card__meta">
                          <div>
                            <dt>Sex</dt>
                            <dd>{sexDisplay(bird)}</dd>
                          </div>
                          <div>
                            <dt>Age</dt>
                            <dd>{bird.age_months} mo</dd>
                          </div>
                          <div>
                            <dt>Base Color</dt>
                            <dd>{bird.base_color?.name || '—'}</dd>
                          </div>
                          <div>
                            <dt>Visual Mutation</dt>
                            <dd>{visualMutationDisplay(bird) || '—'}</dd>
                          </div>
                        </dl>
                      </button>

                      {showHover ? <BirdHoverCard bird={bird} anchor={hoverAnchor} /> : null}

                      <div className="bird-card__actions">
                        <button
                          type="button"
                          className="birds__btn birds__btn--soft"
                          onClick={() => openEditModal(bird)}
                        >
                          Edit
                        </button>
                        <button
                          type="button"
                          className="birds__btn birds__btn--danger"
                          onClick={() => setDeleteTarget(bird)}
                        >
                          Delete
                        </button>
                      </div>
                    </li>
                  )
                })}
              </ul>
            </>
          ) : (
            <div className="bird-table-wrap">
              <table className="bird-table">
                <thead>
                  <tr>
                    <th scope="col">Bird ID</th>
                    <th scope="col">Species</th>
                    <th scope="col">Sex</th>
                    <th scope="col">Age</th>
                    <th scope="col">Base Color</th>
                      <th scope="col">Visual Mutation</th>
                      <th scope="col">Split / Hidden Genes</th>
                      <th scope="col">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  {filteredBirds.map((bird) => {
                    const showHover = hoveredBirdId === bird.id && hoverAnchor && !selectedBirdId
                    return (
                    <tr
                      key={bird.id}
                      onMouseEnter={(event) => {
                        setHoveredBirdId(bird.id)
                        setHoverAnchor(event.currentTarget.getBoundingClientRect())
                      }}
                      onMouseLeave={() => {
                        setHoveredBirdId((current) => (current === bird.id ? null : current))
                        setHoverAnchor(null)
                      }}
                    >
                      {showHover ? <BirdHoverCard bird={bird} anchor={hoverAnchor} /> : null}
                      <td data-label="Bird ID">{bird.bird_id}</td>
                      <td data-label="Species">{speciesDisplay(bird)}</td>
                      <td data-label="Sex">{sexDisplay(bird)}</td>
                      <td data-label="Age">{bird.age_months} mo</td>
                      <td data-label="Base Color">{bird.base_color?.name || '—'}</td>
                        <td data-label="Visual Mutation">{visualMutationDisplay(bird) || '—'}</td>
                        <td data-label="Split / Hidden Genes">{geneDisplay(bird)}</td>
                        <td data-label="Actions">
                        <div className="bird-table__actions">
                          <button
                            type="button"
                            className="birds__btn birds__btn--soft"
                            onClick={() => openEditModal(bird)}
                          >
                            Edit
                          </button>
                          <button
                            type="button"
                            className="birds__btn birds__btn--danger"
                            onClick={() => setDeleteTarget(bird)}
                          >
                            Delete
                          </button>
                        </div>
                      </td>
                    </tr>
                    )
                  })}
                </tbody>
              </table>
            </div>
          )}
        </section>
      </div>

      {selectedBird
        ? createPortal(
            <div className="bird-details-modal" role="presentation">
              <button
                type="button"
                className="bird-details-modal__backdrop"
                aria-label="Close bird details"
                onClick={() => setSelectedBirdId(null)}
              />
              <div
                className="bird-details-modal__dialog"
                role="dialog"
                aria-modal="true"
                aria-labelledby="bird-details-title"
              >
                <BirdDetails bird={selectedBird} onClose={() => setSelectedBirdId(null)} />
              </div>
            </div>,
            document.body,
          )
        : null}

      <BirdFormModal
        open={modalOpen}
        mode={modalMode}
        initialBird={editingBird}
        speciesOptions={speciesOptions}
        baseColors={baseColors}
        visualMutations={visualMutations}
        splitGenes={splitGenes}
        submitting={submitting}
        onClose={closeModal}
        onSubmit={handleSubmit}
      />

      {deleteTarget ? (
        <CenterConfirm
          title="Delete this bird?"
          copy={`Delete ${deleteTarget.bird_id}? This removes the saved profile.`}
          yesLabel="Yes"
          noLabel="No"
          busy={submitting}
          onYes={confirmDelete}
          onNo={() => {
            if (!submitting) setDeleteTarget(null)
          }}
        />
      ) : null}

      {editReview ? (
        <CenterConfirm
          title="Save this edit?"
          copy={`Review the changes for ${editReview.birdId}, then confirm.`}
          yesLabel="Yes"
          noLabel="No"
          busy={submitting}
          onYes={confirmEdit}
          onNo={() => {
            if (!submitting) setEditReview(null)
          }}
        >
          {editReview.error ? <p className="bird-confirm__error">{editReview.error}</p> : null}
          <EditHistoryList changes={editReview.changes} />
        </CenterConfirm>
      ) : null}

      {savedEdit ? (
        <CenterConfirm
          title="Edit history"
          copy={`${savedEdit.birdId} was updated.`}
          yesLabel="Close"
          busy={false}
          onYes={() => setSavedEdit(null)}
          onNo={() => setSavedEdit(null)}
        >
          <EditHistoryList changes={savedEdit.changes} />
        </CenterConfirm>
      ) : null}
    </main>
  )
}
