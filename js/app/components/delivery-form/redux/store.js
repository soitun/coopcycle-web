import { configureStore } from '@reduxjs/toolkit'
import { accountSlice } from '../../../entities/account/reduxSlice'
import { apiSlice } from '../../../api/slice'
import { recurrenceSlice } from './recurrenceSlice'
import { suggestionsSlice } from './suggestionsSlice'

export function createStoreFromPreloadedState(preloadedState) {
  return configureStore({
    reducer: {
      [accountSlice.name]: accountSlice.reducer,
      [apiSlice.reducerPath]: apiSlice.reducer,
      [recurrenceSlice.name]: recurrenceSlice.reducer,
      [suggestionsSlice.name]: suggestionsSlice.reducer,
    },
    preloadedState,
    middleware: getDefaultMiddleware =>
      getDefaultMiddleware().concat(apiSlice.middleware),
  })
}
