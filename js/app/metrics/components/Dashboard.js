import React from 'react'
import { connect } from 'react-redux'

import LogisticsDashboard from './LogisticsDashboard'
import MarketplaceDashboard from './MarketplaceDashboard'
import ZeroWasteDashboard from './ZeroWasteDashboard'
import ProfitabilityDashboard from './ProfitabilityDashboard'
import Navbar from './Navbar'

const Dashboard = ({ cubeApi, view }) => {

  return (
    <React.Fragment>
      <Navbar />
      { view === 'marketplace' && <MarketplaceDashboard cubeApi={ cubeApi } /> }
      { view === 'logistics'   && <LogisticsDashboard cubeApi={ cubeApi } /> }
      { view === 'zerowaste'   && <ZeroWasteDashboard cubeApi={ cubeApi } /> }
      { view === 'profitability' && <ProfitabilityDashboard cubeApi={ cubeApi } /> }
    </React.Fragment>
  )
}

function mapStateToProps(state) {

  return {
    view: state.view,
  }
}

export default connect(mapStateToProps)(Dashboard)
