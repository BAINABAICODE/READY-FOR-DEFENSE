import { useEffect, useRef, useState } from 'react'
import SpeciesSlider from './SpeciesSlider.jsx'
import SliderControls from './SliderControls.jsx'
import SpeciesInfoPanel from './SpeciesInfoPanel.jsx'
import { SPECIES_SLIDES } from './speciesSlides'
import './Homepage.css'

const PARTICLES = Array.from({ length: 14 }, (_, index) => index)

export default function Homepage() {
  const [activeIndex, setActiveIndex] = useState(0)
  const swiperRef = useRef(null)
  const brandRef = useRef(null)
  const titleRef = useRef(null)
  const activeBird = SPECIES_SLIDES[activeIndex]

  const handleSwiper = (swiper) => {
    swiperRef.current = swiper
  }

  const handlePrev = () => swiperRef.current?.slidePrev()
  const handleNext = () => swiperRef.current?.slideNext()
  const handleGoTo = (index) => swiperRef.current?.slideToLoop(index)

  useEffect(() => {
    const brand = brandRef.current
    const title = titleRef.current
    if (!brand || !title) return undefined

    const fitTitle = () => {
      title.style.fontSize = ''
      const styles = window.getComputedStyle(brand)
      const available =
        brand.clientWidth -
        Number.parseFloat(styles.paddingLeft) -
        Number.parseFloat(styles.paddingRight)
      const needed = title.scrollWidth
      if (needed <= available) return
      const current = Number.parseFloat(window.getComputedStyle(title).fontSize)
      title.style.fontSize = `${(current * available) / needed}px`
    }

    const observer = new ResizeObserver(fitTitle)
    observer.observe(brand)
    document.fonts.ready.then(fitTitle)
    window.addEventListener('resize', fitTitle)

    return () => {
      observer.disconnect()
      window.removeEventListener('resize', fitTitle)
    }
  }, [])

  return (
    <main id="home" className="home">
      <section className="hero" aria-labelledby="hero-title">
        <div className="hero__atmosphere" aria-hidden="true">
          <span className="hero__mist hero__mist--a" />
          <span className="hero__mist hero__mist--b" />
          <span className="hero__mist hero__mist--c" />
          <ul className="hero__particles">
            {PARTICLES.map((index) => (
              <li key={index} className="hero__particle" style={{ '--i': index }} />
            ))}
          </ul>
        </div>

        <div className="hero__inner">
          <div className="hero__brand" ref={brandRef}>
            <p className="hero__eyebrow">Lovebird genetic inheritance &amp; breeding computation</p>
            <h1 id="hero-title" className="hero__title" ref={titleRef}>
              AGAPORA
            </h1>
            <p className="hero__lead">Genetic clarity for every Agapornis pairing.</p>
            <p className="hero__intro">
              Discover the nine Agapornis species and explore genetic inheritance, pair compatibility,
              and possible breeding outcomes through a simple, understandable computation system.
            </p>
            <div className="hero__actions">
              <a className="btn btn--gold" href="#breeding">
                Start Breeding
              </a>
            </div>
          </div>

          <div className="hero__stage">
            <SpeciesSlider
              species={SPECIES_SLIDES}
              activeIndex={activeIndex}
              onActiveIndexChange={setActiveIndex}
              onSwiper={handleSwiper}
            />
          </div>

          <div className="hero__nav">
            <SliderControls
              species={SPECIES_SLIDES}
              activeIndex={activeIndex}
              onPrev={handlePrev}
              onNext={handleNext}
              onGoTo={handleGoTo}
            />
          </div>

          <div className="hero__panel">
            <span key={activeBird.id} className="hero__thread" aria-hidden="true" />
            <SpeciesInfoPanel bird={activeBird} position={activeIndex + 1} total={SPECIES_SLIDES.length} />
          </div>
        </div>
      </section>
    </main>
  )
}
