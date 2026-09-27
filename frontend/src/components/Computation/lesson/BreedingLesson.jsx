import { useEffect, useState } from 'react'
import './BreedingLesson.css'

const LESSON_STEPS = [
  { id: 'final-output', n: '01', engine: 'Answer', title: 'The result', detail: 'Should you breed them?' },
  { id: 'flow', n: '02', engine: 'Path', title: 'The path', detail: 'RBGIA, then GICA' },
  { id: 'compatibility', n: '03', engine: 'Start', title: 'The two birds', detail: 'Only saved records' },
  { id: 'inheritance', n: '04', engine: 'RBGIA', title: 'How genes pass', detail: 'Mendel and Punnett' },
  { id: 'distribution', n: '05', engine: 'RBGIA', title: 'Possible chicks', detail: 'Looks and hidden genes' },
  { id: 'gica', n: '06', engine: 'GICA', title: 'The score', detail: 'Why the points land here' },
  { id: 'forecast', n: '07', engine: 'Nest', title: 'Eggs and hatch', detail: 'An estimate' },
  { id: 'complexity', n: '08', engine: 'Extra', title: 'Work this run did', detail: 'Time and space' },
]

const RBGIA_BEATS = [
  { n: '1', title: 'Read the birds', text: 'Color, mutations, and hidden genes already saved. Nothing is invented.' },
  { n: '2', title: 'Translate the alleles', text: 'Each code becomes a symbol: normal, mutation, or W when that side has no gene.' },
  { n: '3', title: 'Pass one copy', text: 'Mendel’s Law of Segregation. A parent carries two copies and gives the chick one.' },
  { n: '4', title: 'Fill the Punnett square', text: 'Cock alleles down the side, hen alleles across the top. Every box is one possible chick.' },
  { n: '5', title: 'Read the look', text: 'Dominant, recessive, or sex-linked rules turn the letters into what you would see.' },
  { n: '6', title: 'Count the odds', text: 'Same boxes are grouped. Those fractions are the inheritance result.' },
]

const GICA_BEATS = [
  { n: '1', title: 'Use the odds as evidence', text: 'GICA reads the RBGIA chances. It does not redraw the squares.' },
  { n: '2', title: 'Weigh six factors', text: 'Desirable traits, recessive risk, diversity, mutation load, species fit, and breeding limits.' },
  { n: '3', title: 'Add to 100', text: 'Each factor has a fixed maximum. Points earned out of that maximum become the score.' },
  { n: '4', title: 'Say what to do', text: 'The score and the reasons become a breeding recommendation.' },
]

const WORDS = [
  { term: 'Allele', meaning: 'One copy of a gene. Each parent gives the chick one allele per gene.' },
  { term: 'Genotype', meaning: 'The letters. This is the genetic code, not the color you see in the aviary.' },
  { term: 'Phenotype', meaning: 'The look. What the bird shows for that gene.' },
  { term: 'Split', meaning: 'A hidden recessive gene. The bird looks normal and can still pass the gene on.' },
  { term: 'Punnett square', meaning: 'The grid of every way the parents’ alleles can meet.' },
]

export function LessonCue({ why, how }) {
  return (
    <div className="lesson-cues">
      <article className="lesson-cue lesson-cue--why">
        <h3>Why</h3>
        <p>{why}</p>
      </article>
      <article className="lesson-cue lesson-cue--how">
        <h3>How</h3>
        <p>{how}</p>
      </article>
    </div>
  )
}

export function MendelianPrimer() {
  return (
    <section className="mendel" aria-labelledby="mendel-title">
      <header className="mendel__head">
        <p className="mendel__kicker">Mendelian rules used by RBGIA</p>
        <h3 id="mendel-title">Three ways a gene can show up in a chick</h3>
        <p>
          Every stored gene follows one of these rules. The Punnett square below applies the rule that belongs to that gene.
        </p>
      </header>
      <div className="mendel__modes">
        <article className="mendel__mode is-dominant">
          <p className="mendel__mode-name">Dominant</p>
          <p>One copy is enough. If either parent passes the mutation, the chick can show it.</p>
        </article>
        <article className="mendel__mode is-recessive">
          <p className="mendel__mode-name">Recessive</p>
          <p>Both parents must pass the mutation before you see it. One copy is a split: hidden, but still heritable.</p>
        </article>
        <article className="mendel__mode is-sex">
          <p className="mendel__mode-name">Sex-linked</p>
          <p>The gene rides the Z chromosome. A cock has two Z copies, so he can hide a recessive allele. A hen has one Z and a W, so she shows the allele on her single Z.</p>
        </article>
      </div>
      <ol className="mendel__laws">
        <li>
          <strong>Law of Segregation.</strong>
          A parent has two copies of a gene and passes only one. That is why the square has the cock’s choices down the side and the hen’s across the top.
        </li>
        <li>
          <strong>Independent assortment.</strong>
          Each gene is crossed on its own. A full chick is those genes combined, which is why one gene’s chance and a whole chick’s chance are not the same bar.
        </li>
        <li>
          <strong>The square is fixed.</strong>
          The same parents always fill the same boxes. RBGIA does not roll a random nest and call that the inheritance.
        </li>
      </ol>
    </section>
  )
}

function BeatList({ items }) {
  return (
    <ol className="lesson-beats">
      {items.map((item) => (
        <li key={item.n}>
          <span>{item.n}</span>
          <div>
            <p className="lesson-beats__title">{item.title}</p>
            <p>{item.text}</p>
          </div>
        </li>
      ))}
    </ol>
  )
}

export default function BreedingLesson({
  parents,
  scoreText,
  status,
  tone = 'muted',
  recommendation,
  onJump,
  children,
}) {
  const [current, setCurrent] = useState(LESSON_STEPS[0].id)

  useEffect(() => {
    const nodes = LESSON_STEPS
      .map((step) => document.getElementById(`process-${step.id}`))
      .filter(Boolean)
    if (!nodes.length) return undefined
    const observer = new IntersectionObserver((entries) => {
      const visible = entries
        .filter((entry) => entry.isIntersecting)
        .sort((a, b) => b.intersectionRatio - a.intersectionRatio)[0]
      if (!visible) return
      const id = visible.target.id.replace(/^process-/, '')
      setCurrent(id)
    }, { rootMargin: '-18% 0px -55% 0px', threshold: [0.12, 0.35] })
    nodes.forEach((node) => observer.observe(node))
    return () => observer.disconnect()
  }, [])

  const handleJump = (id) => {
    setCurrent(id)
    onJump?.(id)
  }

  return (
    <div className="lesson">
      <header className="lesson-hero">
        <div className="lesson-hero__copy">
          <p className="lesson-hero__kicker">{parents}</p>
          <h2>How this pair was worked out</h2>
          <p className="lesson-hero__lede">
            The result comes first, in plain language. Under it, RBGIA shows how genes pass from these two birds to a chick. GICA then turns those chances into one score. The Punnett squares stay as they are.
          </p>
          {recommendation ? <p className="lesson-hero__advice">{recommendation}</p> : null}
        </div>
        <p className={`lesson-hero__score is-${tone}`}>
          <strong>{scoreText}</strong>
          <span>{status}</span>
        </p>
      </header>

      <div className="lesson-engines" aria-label="RBGIA and GICA">
        <article className="lesson-engine lesson-engine--rbgia">
          <header>
            <p className="lesson-engine__mark">RBGIA</p>
            <h3>How a chick inherits genes</h3>
            <p>Rule-Based Genetic Inheritance Analysis. This is the Mendelian cross.</p>
          </header>
          <BeatList items={RBGIA_BEATS} />
          <button type="button" onClick={() => handleJump('inheritance')}>
            Open the Punnett squares
          </button>
        </article>
        <div className="lesson-engines__join" aria-hidden="true">
          <span>odds stay the same</span>
        </div>
        <article className="lesson-engine lesson-engine--gica">
          <header>
            <p className="lesson-engine__mark">GICA</p>
            <h3>Whether this pair should be bred</h3>
            <p>Genetic Inheritance Compatibility Analysis. This is the score, not a second cross.</p>
          </header>
          <BeatList items={GICA_BEATS} />
          <button type="button" onClick={() => handleJump('gica')}>
            Open the weights
          </button>
        </article>
      </div>

      <dl className="lesson-words">
        {WORDS.map((word) => (
          <div key={word.term}>
            <dt>{word.term}</dt>
            <dd>{word.meaning}</dd>
          </div>
        ))}
      </dl>

      <nav className="lesson-nav" aria-label="Breeding lesson">
        {LESSON_STEPS.map((step) => {
          const selected = current === step.id
          return (
            <button
              key={step.id}
              type="button"
              className={`lesson-nav__btn${selected ? ' is-current' : ''}`}
              aria-current={selected ? 'true' : undefined}
              onClick={() => handleJump(step.id)}
            >
              <span className="lesson-nav__n">{step.n}</span>
              <span className="lesson-nav__copy">
                <span className="lesson-nav__engine">{step.engine}</span>
                <span className="lesson-nav__title">{step.title}</span>
              </span>
            </button>
          )
        })}
      </nav>

      <div className="lesson-stack">
        {children}
      </div>
    </div>
  )
}
