import { useMemo } from 'react'
import { buildCompatibilityModel, buildRbgiaTrace, collectValidationFindings, summariseConfidence } from '../../services/genetics'
import Disclosure from './shared/Disclosure'
import CompatibilitySummary from './compatibility/CompatibilitySummary'
import WeightedFormula from './compatibility/WeightedFormula'
import ScoreBreakdown from './compatibility/ScoreBreakdown'
import SpeciesCompatibilityPanel from './compatibility/SpeciesCompatibilityPanel'
import ParentGeneticProfile from './compatibility/ParentGeneticProfile'
import GeneticValidation from './compatibility/GeneticValidation'
import PredictionBasis from './compatibility/PredictionBasis'
import RbgiaPipeline from './inheritance/RbgiaPipeline'
import SexLinkedAnalysis from './inheritance/SexLinkedAnalysis'
import InheritanceBreakdown from './inheritance/InheritanceBreakdown'
import RbgiaSummary from './inheritance/RbgiaSummary'
import OutcomeDistribution from './outcomes/OutcomeDistribution'
import OutcomeCards from './outcomes/OutcomeCards'
import { ProbabilityBar } from './shared/primitives'
import { barColor, geneInheritanceRows } from './shared/format'
import ClutchInterpretation from './outcomes/ClutchInterpretation'
import './CompatibilityModule.css'

export default function CompatibilityModule({ result }) {
  const presentation = result?.result_presentation || {}

  const model = useMemo(
    () => buildCompatibilityModel(presentation.gica || {}, presentation.species_compatibility || {}),
    [presentation.gica, presentation.species_compatibility],
  )
  const trace = useMemo(() => buildRbgiaTrace(result || {}), [result])
  const findings = useMemo(() => collectValidationFindings({ result: result || {}, lociTraces: trace.loci }), [result, trace.loci])
  const confidence = useMemo(() => summariseConfidence({ result: result || {}, lociTraces: trace.loci, findings }), [result, trace.loci, findings])

  const errorCount = findings.filter((f) => f.severity === 'error').length
  const warningCount = findings.filter((f) => f.severity === 'warning').length

  return (
    <div className="gx-module">
      <section className="gx-block gx-block--hero" aria-labelledby="gx-compat-title">
        <p className="compute-result__eyebrow">Pairing compatibility</p>
        <h2 id="gx-compat-title">Deterministic compatibility analysis</h2>
        <p className="gx-block__lead">
          Genetic Inheritance Compatibility Analysis (GICA) scores this pair from stored parental records using fixed, rule-based
          factors. Every value below is computed from the stored records for Parent 1 and Parent 2.
        </p>
        <CompatibilitySummary model={model} rbgiaSummary={{ ...trace.summary, lociTotal: trace.loci.length }} confidence={confidence} />
      </section>

      <Disclosure icon="📐" title="Weighted Compatibility Formula" eyebrow="GICA" badge={`${model.factors.length} factors · weights sum ${Math.round(model.weightsSumPercent)}%`}>
        <WeightedFormula model={model} />
      </Disclosure>

      <Disclosure icon="🔎" title="View Score Computation" eyebrow="Score computation trace" badge={`${model.computedTotal.toFixed(2)} / 100`}>
        <ScoreBreakdown model={model} trace={trace} />
      </Disclosure>

      <Disclosure icon="🧭" title="Species Compatibility" eyebrow="Rule check" defaultOpen={model.species.interspecific} badge={model.species.sameSpecies ? 'Same species' : model.species.label}>
        <SpeciesCompatibilityPanel species={model.species} />
      </Disclosure>

      <Disclosure icon="🧬" title="Parent Genetic Profiles" eyebrow="Input data">
        <ParentGeneticProfile snapshot={result?.parent_snapshot} species={model.species} />
      </Disclosure>

      <section className="gx-block gx-block--rbgia" aria-labelledby="gx-rbgia-title">
        <p className="compute-result__eyebrow">Predicted offspring outcomes</p>
        <h2 id="gx-rbgia-title">RBGIA — Rule-Based Genetic Inheritance Analysis</h2>
        <p className="gx-block__lead">
          RBGIA evaluates parental alleles using deterministic inheritance rules, gamete formation, Punnett-square combinations,
          inheritance-mode classification, and phenotype mapping. The same parental records always yield the same result.
        </p>
        <h3 className="gx-block__subtitle">Visual appearance distribution</h3>
        <p className="gx-note gx-note--lead">Base colour + expressed visual mutation, pooled across sex and hidden splits ({trace.appearanceOutcomes.length} classes).</p>
        <OutcomeDistribution groups={trace.appearanceOutcomes} total={trace.joint.total} source={trace.joint.source} tone="brand" />
        <h3 className="gx-block__subtitle gx-block__subtitle--spaced">Chance of each gene</h3>
        <p className="gx-note gx-note--lead">
          Each bar is one gene, not one full chick. A gene every chick inherits is 100%. A gene only half the chicks inherit is 50%. These lengths are not the same as the chick list.
        </p>
        {['base_color', 'visual_mutation', 'split_gene'].map((kind) => {
          const title = kind === 'base_color' ? 'Base color' : kind === 'visual_mutation' ? 'Visual mutation' : 'Hidden gene'
          const rows = geneInheritanceRows(trace.joint.rows, kind)
          if (!rows.length) return null
          return (
            <div key={kind}>
              <h4 className="gx-block__subtitle gx-block__subtitle--spaced">{title}</h4>
              <ol className="gx-distribution__list">
                {rows.map((row, index) => (
                  <li key={`${kind}-${row.trait}`} className="gx-distribution__row">
                    <div className="gx-distribution__label">
                      <span className="gx-distribution__name">{row.trait}</span>
                    </div>
                    <ProbabilityBar probability={row.probability} label={row.trait} color={barColor(row.trait, index)} />
                  </li>
                ))}
              </ol>
            </div>
          )
        })}
        <h3 className="gx-block__subtitle gx-block__subtitle--spaced">Full predicted offspring distribution</h3>
        <p className="gx-note gx-note--lead">Every distinct sex + visual + split combination the engine produced ({trace.visualOutcomes.length} outcomes). When three genes each split in half, every full combination has the same chance. Each bar has its own color so the chicks stay distinct.</p>
        <OutcomeDistribution groups={trace.visualOutcomes} total={trace.joint.total} source={trace.joint.source} />
      </section>

      <Disclosure icon="📐" title="RBGIA Computation" eyebrow="F1 – F5 pipeline per locus" badge={`${trace.loci.length} loci · ${trace.summary.lociVerified}/${trace.summary.lociWithStored} verified`}>
        <RbgiaPipeline loci={trace.loci} />
      </Disclosure>

      <Disclosure icon="🧬" title="Sex-Linked Inheritance Analysis" eyebrow="Avian ZW model" badge={trace.sexLinkedLoci.length ? `${trace.sexLinkedLoci.length} Z-linked loci` : 'None detected'}>
        <SexLinkedAnalysis loci={trace.sexLinkedLoci} />
      </Disclosure>

      <Disclosure icon="📋" title="Inheritance Breakdown" eyebrow="Per-locus table" badge={`${trace.breakdownRows.length} rows`}>
        <InheritanceBreakdown rows={trace.breakdownRows} />
      </Disclosure>

      <section className="gx-block" aria-labelledby="gx-cards-title">
        <p className="compute-result__eyebrow">Outcome cards</p>
        <h3 id="gx-cards-title" className="gx-block__subtitle">Predicted offspring — one card per joint genotype</h3>
        <OutcomeCards rows={trace.joint.rows} />
      </section>

      <Disclosure icon="🐣" title="Expected Clutch Interpretation" eyebrow="Theoretical distribution">
        <ClutchInterpretation appearanceOutcomes={trace.appearanceOutcomes} />
      </Disclosure>

      <Disclosure
        icon="⚠"
        title="Genetic Validation"
        eyebrow="Limitations & data checks"
        defaultOpen={errorCount > 0}
        badge={findings.length ? `${errorCount} error · ${warningCount} warning · ${findings.length - errorCount - warningCount} notice` : 'No issues'}
      >
        <GeneticValidation findings={findings} />
      </Disclosure>

      <Disclosure icon="✓" title="Prediction Basis & Confidence" eyebrow="How to read these numbers" badge={confidence.overall}>
        <PredictionBasis confidence={confidence} summary={trace.summary} />
      </Disclosure>

      <section className="gx-block gx-block--final" aria-labelledby="gx-final-title">
        <p className="compute-result__eyebrow">Final RBGIA summary</p>
        <h3 id="gx-final-title" className="gx-block__subtitle">Summary of the deterministic analysis</h3>
        <RbgiaSummary trace={trace} />
      </section>
    </div>
  )
}
