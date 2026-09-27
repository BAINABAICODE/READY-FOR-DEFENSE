import { buildPhenotypeRegions } from '../../../services/genetics/phenotypeRegions'
import { plumageColor } from '../../../services/genetics/plumageColors'

export default function PhenotypeMap({ source, title = 'Look · head to tail' }) {
  const regions = buildPhenotypeRegions(source || {})
  if (!regions.some((region) => region.specified) && !source?.phenotype) return null

  return (
    <section className="phenotype-map" aria-label={title}>
      <p className="phenotype-map__kicker">{title}</p>
      <ol className="phenotype-map__list">
        {regions.map((region) => {
          const fill = plumageColor(region.value)
          return (
            <li
              key={region.key}
              className={`phenotype-map__row${region.specified ? ' is-set' : ''}${fill ? ' has-color' : ''}`}
              style={fill ? { '--region-color': fill } : undefined}
            >
              <span className="phenotype-map__label">{region.label}</span>
              <span className="phenotype-map__value">{region.value}</span>
            </li>
          )
        })}
      </ol>
    </section>
  )
}
