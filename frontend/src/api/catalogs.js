import api from './client'

const CATALOG_TTL_MS = 30 * 60 * 1000
const BIRD_TTL_MS = 5 * 60 * 1000

const catalogStored = new Map()
const catalogInflight = new Map()

let birdsStored = null
let birdsInflight = null

export function getCatalog(path) {
  const hit = catalogStored.get(path)
  if (hit && hit.expiresAt > Date.now()) {
    return Promise.resolve(hit.response)
  }

  const pending = catalogInflight.get(path)
  if (pending) return pending

  const request = api
    .get(path)
    .then((response) => {
      catalogStored.set(path, { response, expiresAt: Date.now() + CATALOG_TTL_MS })
      return response
    })
    .finally(() => {
      catalogInflight.delete(path)
    })

  catalogInflight.set(path, request)
  return request
}

export function getBirds({ fresh = false } = {}) {
  if (!fresh && birdsStored && birdsStored.expiresAt > Date.now()) {
    return Promise.resolve(birdsStored.response)
  }

  if (!fresh && birdsInflight) return birdsInflight

  const request = api
    .get('/birds')
    .then((response) => {
      birdsStored = { response, expiresAt: Date.now() + BIRD_TTL_MS }
      return response
    })
    .finally(() => {
      if (birdsInflight === request) birdsInflight = null
    })

  birdsInflight = request
  return request
}
