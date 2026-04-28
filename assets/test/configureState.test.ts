import { describe, expect, it } from 'vitest'
import { configureState } from '../src/functions/configureState'

describe('configureState', () => {
    it('does nothing when state key is absent', () => {
        const payload: Record<string, any> = { pageLength: 25 }
        configureState(payload)
        expect(payload).toEqual({ pageLength: 25 })
    })

    it('sets stateSave and removes the state key', () => {
        const payload: Record<string, any> = { state: { duration: 7200 } }
        configureState(payload)
        expect(payload.stateSave).toBe(true)
        expect(payload.state).toBeUndefined()
    })

    it('passes stateDuration from state config', () => {
        const payload: Record<string, any> = { state: { duration: 3600 } }
        configureState(payload)
        expect(payload.stateDuration).toBe(3600)
    })

    it('passes duration -1 for sessionStorage', () => {
        const payload: Record<string, any> = { state: { duration: -1 } }
        configureState(payload)
        expect(payload.stateSave).toBe(true)
        expect(payload.stateDuration).toBe(-1)
    })

    it('passes duration 0 for indefinite localStorage', () => {
        const payload: Record<string, any> = { state: { duration: 0 } }
        configureState(payload)
        expect(payload.stateSave).toBe(true)
        expect(payload.stateDuration).toBe(0)
    })

    it('preserves other payload keys', () => {
        const payload: Record<string, any> = { pageLength: 25, state: { duration: 7200 } }
        configureState(payload)
        expect(payload.pageLength).toBe(25)
    })
})