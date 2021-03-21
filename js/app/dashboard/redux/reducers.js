import _ from 'lodash'

import {
  OPEN_ADD_USER,
  CLOSE_ADD_USER,
  TOGGLE_POLYLINE,
  TOGGLE_TASK,
  SELECT_TASK,
  SELECT_TASKS,
  SET_TASK_LIST_GROUP_MODE,
  OPEN_NEW_TASK_MODAL,
  CLOSE_NEW_TASK_MODAL,
  SET_CURRENT_TASK,
  CREATE_TASK_REQUEST,
  CREATE_TASK_SUCCESS,
  CREATE_TASK_FAILURE,
  COMPLETE_TASK_FAILURE,
  CANCEL_TASK_FAILURE,
  TOKEN_REFRESH_SUCCESS,
  OPEN_FILTERS_MODAL,
  CLOSE_FILTERS_MODAL,
  TOGGLE_SEARCH,
  OPEN_SEARCH,
  CLOSE_SEARCH,
  OPEN_SETTINGS,
  CLOSE_SETTINGS,
  SET_POLYLINE_STYLE,
  LOAD_TASK_EVENTS_REQUEST,
  LOAD_TASK_EVENTS_SUCCESS,
  LOAD_TASK_EVENTS_FAILURE,
  ADD_IMPORT,
  IMPORT_SUCCESS,
  IMPORT_ERROR,
  OPEN_IMPORT_MODAL,
  CLOSE_IMPORT_MODAL,
  SET_CLUSTERS_ENABLED,
  CLEAR_SELECTED_TASKS,
  MODIFY_TASK_LIST_REQUEST_SUCCESS,
  RIGHT_PANEL_MORE_THAN_HALF,
  RIGHT_PANEL_LESS_THAN_HALF,
  OPEN_RECURRENCE_RULE_MODAL,
  CLOSE_RECURRENCE_RULE_MODAL,
  SET_CURRENT_RECURRENCE_RULE,
  UPDATE_RECURRENCE_RULE_SUCCESS,
  UPDATE_RECURRENCE_RULE_REQUEST,
  DELETE_RECURRENCE_RULE_SUCCESS,
  UPDATE_RECURRENCE_RULE_ERROR,
  OPEN_EXPORT_MODAL,
  CLOSE_EXPORT_MODAL,
} from './actions'

import {
  recurrenceRulesAdapter,
} from './selectors'

const initialState = {
  addModalIsOpen: false,
  polylineEnabled: {},
  taskListGroupMode: 'GROUP_MODE_FOLDERS',
  selectedTasks: [],
  jwt: '',
  positions: [],
  offline: [],
  taskModalIsOpen: false,
  isTaskModalLoading: false,
  couriersList: [],
  completeTaskErrorMessage: null,
  filtersModalIsOpen: false,
  settingsModalIsOpen: false,
  polylineStyle: 'normal',
  searchIsOn: false,
  isLoadingTaskEvents: false,
  taskEvents: {},
  imports: {},
  importModalIsOpen: false,
  clustersEnabled: false,
  rightPanelSplitDirection: 'vertical',
  recurrenceRuleModalIsOpen: false,
  currentRecurrenceRule: null,
  rrules: recurrenceRulesAdapter.getInitialState(),
  recurrenceRulesLoading: false,
  recurrenceRulesErrorMessage: '',
  exportModalIsOpen: false,
}

const addModalIsOpen = (state = false, action) => {
  switch(action.type) {
  case OPEN_ADD_USER:
    return true
  case CLOSE_ADD_USER:
    return false
  default:
    return state
  }
}

const polylineEnabled = (state = {}, action) => {
  switch (action.type) {
  case TOGGLE_POLYLINE:
    let newState = { ...state }
    const { username } = action
    newState[username] = !state[username]

    return newState
  default:
    return state
  }
}

const selectedTasks = (state = [], action) => {

  let newState = state.slice(0)

  switch (action.type) {
  case TOGGLE_TASK:

    if (-1 !== state.indexOf(action.task)) {
      if (!action.multiple) {
        return []
      }
      return _.filter(state, task => task !== action.task)
    }

    if (!action.multiple) {
      newState = []
    }
    newState.push(action.task)

    return newState

  case SELECT_TASK:

    if (-1 !== state.indexOf(action.task)) {

      return state
    }

    return [ action.task ]

  case SELECT_TASKS:

    return action.tasks

  case CLEAR_SELECTED_TASKS:
  case MODIFY_TASK_LIST_REQUEST_SUCCESS:

    // OPTIMIZATION
    // Make sure the array if not already empty
    // before returning a new reference
    if (state.length > 0) {
      return []
    }
    break
  }

  return state
}

const taskListGroupMode = (state = 'GROUP_MODE_FOLDERS', action) => {
  switch (action.type) {
  case SET_TASK_LIST_GROUP_MODE:
    return action.mode
  default:
    return state
  }
}

const jwt = (state = '', action) => {
  switch (action.type) {
  case TOKEN_REFRESH_SUCCESS:

    return action.token

  default:

    return state
  }
}

const taskModalIsOpen = (state = false, action) => {
  switch(action.type) {
  case OPEN_NEW_TASK_MODAL:
    return true
  case CLOSE_NEW_TASK_MODAL:
    return false
  case SET_CURRENT_TASK:

    if (!!action.task) {
      return true
    }

    return false
  default:
    return state
  }
}

const isTaskModalLoading = (state = false, action) => {
  switch(action.type) {
  case CREATE_TASK_REQUEST:
    return true
  case CREATE_TASK_SUCCESS:
  case CREATE_TASK_FAILURE:
  case COMPLETE_TASK_FAILURE:
  case CANCEL_TASK_FAILURE:
    return false
  default:
    return state
  }
}

const completeTaskErrorMessage = (state = null, action) => {
  switch(action.type) {
  case CREATE_TASK_REQUEST:
  case CREATE_TASK_SUCCESS:
    return null
  case COMPLETE_TASK_FAILURE:

    const { error } = action

    if (error.response) {
      // The request was made and the server responded with a status code
      // that falls out of the range of 2xx
      if (error.response.status === 400) {
        if (Object.prototype.hasOwnProperty.call(error.response.data, '@type') && error.response.data['@type'] === 'hydra:Error') {
          return error.response.data['hydra:description']
        }
      }
    } else if (error.request) {
      // The request was made but no response was received
      // `error.request` is an instance of XMLHttpRequest in the browser and an instance of
      // http.ClientRequest in node.js
    } else {
      // Something happened in setting up the request that triggered an Error
    }

    break
  }

  return state
}

const filtersModalIsOpen = (state = initialState.filtersModalIsOpen, action) => {
  switch (action.type) {
  case OPEN_FILTERS_MODAL:
    return true
  case CLOSE_FILTERS_MODAL:
    return false
  default:
    return state
  }
}

const searchIsOn = (state = initialState.searchIsOn, action) => {
  switch (action.type) {
  case TOGGLE_SEARCH:

    return !state
  case OPEN_SEARCH:

    return true
  case CLOSE_SEARCH:

    return false
  default:
    return state
  }
}

const settingsModalIsOpen = (state = initialState.settingsModalIsOpen, action) => {
  switch (action.type) {
  case OPEN_SETTINGS:

    return true
  case CLOSE_SETTINGS:

    return false
  default:
    return state
  }
}

const importModalIsOpen = (state = false, action) => {
  switch(action.type) {
  case OPEN_IMPORT_MODAL:
    return true
  case CLOSE_IMPORT_MODAL:
    return false
  default:
    return state
  }
}

const polylineStyle = (state = initialState.polylineStyle, action) => {
  switch (action.type) {
  case SET_POLYLINE_STYLE:

    return action.style
  default:
    return state
  }
}

const isLoadingTaskEvents = (state = initialState.isLoadingTaskEvents, action) => {
  switch (action.type) {
  case LOAD_TASK_EVENTS_REQUEST:

    return true
  case LOAD_TASK_EVENTS_SUCCESS:
  case LOAD_TASK_EVENTS_FAILURE:

    return false
  }

  return state
}

const taskEvents = (state = initialState.taskEvents, action) => {
  switch (action.type) {
  case LOAD_TASK_EVENTS_SUCCESS:
    return {
      ...state,
      [action.task['@id']]: action.events
    }
  }

  return state
}

const imports = (state = initialState.imports, action) => {
  switch (action.type) {
  case ADD_IMPORT:
    return {
      ...state,
      [ action.token ]: '',
    }
  case IMPORT_SUCCESS:
    return _.omit(state, [ action.token ])
  case IMPORT_ERROR:
    return {
      ...state,
      [ action.token ]: action.message,
    }
  case OPEN_IMPORT_MODAL:
    return {}
  }

  return state
}

const clustersEnabled = (state = initialState.clustersEnabled, action) => {
  switch (action.type) {
  case SET_CLUSTERS_ENABLED:

    return action.enabled
  }

  return state
}

const rightPanelSplitDirection = (state = initialState.rightPanelSplitDirection, action) => {
  switch (action.type) {
  case RIGHT_PANEL_MORE_THAN_HALF:

    return 'horizontal'
  case RIGHT_PANEL_LESS_THAN_HALF:

    return 'vertical'
  }

  return state
}

const recurrenceRuleModalIsOpen = (state = false, action) => {
  switch(action.type) {
  case OPEN_RECURRENCE_RULE_MODAL:
    return true
  case CLOSE_RECURRENCE_RULE_MODAL:
    return false
  case SET_CURRENT_RECURRENCE_RULE:

    if (!!action.recurrenceRule) {
      return true
    }

    return false
  }

  return state
}

const currentRecurrenceRule = (state = null, action) => {
  switch(action.type) {
  case SET_CURRENT_RECURRENCE_RULE:

    return action.recurrenceRule
  case CLOSE_RECURRENCE_RULE_MODAL:
    return null
  }

  return state
}

const rrules = (state = initialState.rrules, action) => {
  switch(action.type) {
  case UPDATE_RECURRENCE_RULE_SUCCESS:

    return recurrenceRulesAdapter.upsertOne(state, action.recurrenceRule)
  case DELETE_RECURRENCE_RULE_SUCCESS:

    return recurrenceRulesAdapter.removeOne(state, action.recurrenceRule)
  }

  return state
}

const recurrenceRulesLoading = (state = initialState.recurrenceRulesLoading, action) => {
  switch(action.type) {
  case UPDATE_RECURRENCE_RULE_REQUEST:

    return true
  case UPDATE_RECURRENCE_RULE_SUCCESS:
  case DELETE_RECURRENCE_RULE_SUCCESS:
  case UPDATE_RECURRENCE_RULE_ERROR:

    return false
  }

  return state
}

const recurrenceRulesErrorMessage = (state = initialState.recurrenceRulesErrorMessage, action) => {
  switch(action.type) {
  case UPDATE_RECURRENCE_RULE_REQUEST:
  case UPDATE_RECURRENCE_RULE_SUCCESS:
  case DELETE_RECURRENCE_RULE_SUCCESS:

    return ''
  case UPDATE_RECURRENCE_RULE_ERROR:

    return action.message
  }

  return state
}

const exportModalIsOpen = (state = false, action) => {
  switch(action.type) {
  case OPEN_EXPORT_MODAL:
    return true
  case CLOSE_EXPORT_MODAL:
    return false
  default:
    return state
  }
}

export default (state = initialState, action) => {

  return {
    ...state,
    addModalIsOpen: addModalIsOpen(state.addModalIsOpen, action),
    polylineEnabled: polylineEnabled(state.polylineEnabled, action),
    taskListGroupMode: taskListGroupMode(state.taskListGroupMode, action),
    selectedTasks: selectedTasks(state.selectedTasks, action),
    jwt: jwt(state.jwt, action),
    taskModalIsOpen: taskModalIsOpen(state.taskModalIsOpen, action),
    isTaskModalLoading: isTaskModalLoading(state.isTaskModalLoading, action),
    completeTaskErrorMessage: completeTaskErrorMessage(state.completeTaskErrorMessage, action),
    filtersModalIsOpen: filtersModalIsOpen(state.filtersModalIsOpen, action),
    searchIsOn: searchIsOn(state.searchIsOn, action),
    settingsModalIsOpen: settingsModalIsOpen(state.settingsModalIsOpen, action),
    polylineStyle: polylineStyle(state.polylineStyle, action),
    isLoadingTaskEvents: isLoadingTaskEvents(state.isLoadingTaskEvents, action),
    taskEvents: taskEvents(state.taskEvents, action),
    imports: imports(state.imports, action),
    importModalIsOpen: importModalIsOpen(state.importModalIsOpen, action),
    clustersEnabled: clustersEnabled(state.clustersEnabled, action),
    rightPanelSplitDirection: rightPanelSplitDirection(state.rightPanelSplitDirection, action),
    recurrenceRuleModalIsOpen: recurrenceRuleModalIsOpen(state.recurrenceRuleModalIsOpen, action),
    currentRecurrenceRule: currentRecurrenceRule(state.currentRecurrenceRule, action),
    rrules: rrules(state.rrules, action),
    recurrenceRulesLoading: recurrenceRulesLoading(state.recurrenceRulesLoading, action),
    recurrenceRulesErrorMessage: recurrenceRulesErrorMessage(state.recurrenceRulesErrorMessage, action),
    exportModalIsOpen: exportModalIsOpen(state.exportModalIsOpen, action),
  }
}
