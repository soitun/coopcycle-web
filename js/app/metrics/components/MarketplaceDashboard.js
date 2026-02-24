import React from 'react'
import { connect } from 'react-redux'

import BestRestaurants from './BestRestaurants'
import AverageCart from './AverageCart'
import OrderCountPerDayOfWeek from './OrderCountPerDayOfWeek'
import OrderCountPerHourRange from './OrderCountPerHourRange'
import OrderCountPerZone from './OrderCountPerZone'
import ChartPanel from './ChartPanel'

const Dashboard = ({ cubeApi, dateRange }) => {

  return (
    <div>
      <div className="metrics-grid">
        <ChartPanel title="Best restaurants">
          <BestRestaurants cubeApi={ cubeApi } dateRange={ dateRange } />
        </ChartPanel>
        <ChartPanel title="Average order total">
          <AverageCart cubeApi={ cubeApi } dateRange={ dateRange } />
        </ChartPanel>
        <ChartPanel title="Number of orders per day of week">
          <OrderCountPerDayOfWeek cubeApi={ cubeApi } dateRange={ dateRange } />
        </ChartPanel>
        <ChartPanel title="Number of orders per hour range">
          <OrderCountPerHourRange cubeApi={ cubeApi } dateRange={ dateRange } />
        </ChartPanel>
        <ChartPanel title="Number of orders per zone">
          <OrderCountPerZone cubeApi={ cubeApi } dateRange={ dateRange } />
        </ChartPanel>
      </div>
    </div>
  )
}

function mapStateToProps(state) {

  return {
    dateRange: state.dateRange,
  }
}

export default connect(mapStateToProps)(Dashboard)
