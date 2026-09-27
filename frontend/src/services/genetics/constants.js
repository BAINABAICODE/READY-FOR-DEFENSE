/**
 * Centralised, presentation-side constants for the compatibility / RBGIA modules.
 * The backend (GicaAnalyzer, RbgiaPredictor) remains the single source of truth for
 * every computed value; these constants only mirror its published scale so the UI
 * never scatters magic numbers. When the API ships `gica.classification_scale`, the
 * calculator prefers that over this fallback.
 */

export const COMPATIBILITY_CLASSIFICATION = Object.freeze([
  { label: 'Excellent', min: 90, max: 100, tone: 'full', guidance: 'Highly Compatible' },
  { label: 'Good', min: 75, max: 89, tone: 'high', guidance: 'Compatible' },
  { label: 'Fair', min: 60, max: 74, tone: 'moderate', guidance: 'Moderately Compatible' },
  { label: 'Poor', min: 40, max: 59, tone: 'low', guidance: 'Low Compatibility' },
  { label: 'Not Recommended', min: 0, max: 39, tone: 'poor', guidance: 'Not Recommended' },
])

export const GICA_TOTAL_POINTS = 100

/** Weight symbols shown in the formula view, keyed by the backend factor key. */
export const GICA_FACTOR_SYMBOLS = Object.freeze({
  species_compatibility: 'Ws',
  inheritance_information: 'Wi',
  mutation_compatibility: 'Wm',
  genetic_risk: 'Wr',
  genetic_diversity: 'Wg',
  breeding_constraints: 'Wc',
})

export const INHERITANCE_MODES = Object.freeze({
  AUTOSOMAL_DOMINANT: 'autosomal_dominant',
  AUTOSOMAL_RECESSIVE: 'autosomal_recessive',
  INCOMPLETE_DOMINANT: 'incomplete_dominant',
  SEX_LINKED_RECESSIVE: 'sex_linked_recessive',
  SEX_LINKED_INCOMPLETE_DOMINANT: 'sex_linked_incomplete_dominant',
  SEX_LINKED_DOMINANT: 'sex_linked_dominant',
  WILD_TYPE: 'wild_type',
  CHROMOSOMAL_SEX: 'chromosomal_sex',
  UNKNOWN: 'unknown',
})

export const INHERITANCE_MODE_LABELS = Object.freeze({
  autosomal_dominant: 'Autosomal Dominant',
  autosomal_recessive: 'Autosomal Recessive',
  incomplete_dominant: 'Incomplete / Intermediate Dominant',
  sex_linked_recessive: 'Sex-Linked (Z-linked) Recessive',
  sex_linked_incomplete_dominant: 'Sex-Linked (Z-linked) Incomplete Dominant',
  sex_linked_dominant: 'Sex-Linked (Z-linked) Dominant',
  wild_type: 'Wild Type (fixed locus)',
  chromosomal_sex: 'Chromosomal Sex (ZZ / ZW)',
  unknown: 'Unknown / Needs Verification',
})

export const EXPRESSION_LABELS = Object.freeze({
  non_carrier: 'Non-carrier (wild type)',
  carrier_split: 'Carrier / split (hidden)',
  visual: 'Visual',
  visual_homozygous: 'Visual (homozygous)',
  visual_heterozygous: 'Visual (heterozygous, single factor)',
  visual_hemizygous: 'Visual (hemizygous hen)',
  hemizygous_wild: 'Wild type (hemizygous hen)',
  visual_single_factor: 'Visual (single factor)',
  visual_double: 'Visual (double factor)',
  visual_double_factor: 'Visual (double factor)',
  visual_compound: 'Visual (allelic compound)',
})

export const SEX_CHROMOSOMES = Object.freeze({
  cock: 'ZZ',
  hen: 'ZW',
})

export const PREDICTION_CONFIDENCE = Object.freeze({
  HIGH: 'High',
  MODERATE: 'Moderate',
  LIMITED: 'Limited',
})

export const DEFAULT_CLUTCH_SIZES = Object.freeze([3, 5, 7])

export const PIPELINE_STEPS = Object.freeze([
  'Parent input', 'Genetic normalisation', 'Allele encoding', 'Inheritance-mode detection', 'Gamete formation',
  'Punnett combination', 'Genotype resolution', 'Phenotype mapping', 'Probability aggregation', 'Sex determination',
  'Compatibility analysis', 'Outcome cards', 'Formula / computation trace',
])

export const CLUTCH_DISCLAIMER =
  'Expected theoretical distribution only. Actual clutch results may vary due to biological randomness, fertility, viability, environmental factors, and other factors.'
