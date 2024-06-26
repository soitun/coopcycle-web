import React from 'react'
import { connect } from 'react-redux'
import { withTranslation } from 'react-i18next'
import _ from 'lodash'
import { Input } from 'antd'

import { toggleSearch } from '../redux/actions'
import { selectFuseSearch, selectSearchIsOn } from '../redux/selectors'

import Task from './Task'

const { Search } = Input

import 'antd/lib/button/style/index.css'

class SearchPanel extends React.Component {

  constructor(props) {
    super(props)

    this.state = {
      q: '',
      results: []
    }

    this.searchRef = React.createRef()
    this.search = _.debounce(this._search.bind(this), 100)

  }

  componentDidUpdate(prevProps) {
    if (!prevProps.searchIsOn && this.props.searchIsOn) {
      setTimeout(() => this.searchRef.current.focus(), 400)
    }
    if (prevProps.searchIsOn && !this.props.searchIsOn) {
      this.setState({
        q: '',
        results: []
      })
    }
  }

  _search(q) {
    const results = this.props.fuse.search(q)
    this.setState({
      results: results.map(result => result.item)
    })
  }

  render () {

    const classNames = [
      'dashboard__panel',
      'dashboard__panel__search'
    ]

    if (this.props.searchIsOn) {
      classNames.push('dashboard__panel__search--on')
    }

    return (
      <div className={ classNames.join(' ') }>
        <h4 className="dashboard__panel__header">
          <span>{ this.props.t('ADMIN_DASHBOARD_SEARCH') }</span>
          <span className="pull-right">
            <a href="#" onClick={ e => {
              e.preventDefault()
              this.props.toggleSearch()
            }}>
              <i className="fa fa-close" data-cypress-close-search></i>
            </a>
          </span>
        </h4>
        <div className="dashboard__panel__search-box">
          <Search
            value={ this.state.q }
            placeholder={ this.props.t('ADMIN_DASHBOARD_SEARCH_PLACEHOLDER') }
            onSearch={ value => {
              this.setState({ q: value })
              this.search(value)
            }}
            onChange={ e => {
              this.setState({ q: e.target.value })
              this.search(e.target.value)
            }}
            ref={ this.searchRef }
          />
        </div>
        <div className="dashboard__panel__scroll">
          <div className="list-group nomargin">
            { _.map(this.state.results, (task, key) => {
              return (
                <Task
                  key={ key }
                  taskId={ task['@id'] }
                  toggleTask={ this.props.toggleTask }
                  selectTask={ this.props.selectTask }
                  taskWithoutDrag
                />
              )
            })}
          </div>
        </div>
      </div>
    )
  }
}

function mapStateToProps(state) {

  return {
    fuse: selectFuseSearch(state),
    searchIsOn: selectSearchIsOn(state)
  }
}

function mapDispatchToProps(dispatch) {

  return {
    toggleSearch: () => dispatch(toggleSearch())
  }
}

export default connect(mapStateToProps, mapDispatchToProps)(withTranslation()(SearchPanel))
