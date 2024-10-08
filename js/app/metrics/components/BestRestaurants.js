import React from 'react'
import { QueryRenderer } from '@cubejs-client/react';
import { Spin } from 'antd';
import 'chart.js/auto'; // ideally we should only import the component that we need: https://react-chartjs-2.js.org/docs/migration-to-v4/#tree-shaking
import { Pie } from 'react-chartjs-2';
import chroma from 'chroma-js'

const commonOptions = {
  maintainAspectRatio: false,
};

import { getCubeDateRange } from '../utils'

const renderChart = ({ resultSet, error }) => {

  if (error) {
    return <div>{error.toString()}</div>;
  }

  if (!resultSet) {
    return <Spin />;
  }

  const data = {
    labels: resultSet.categories().map((c) => c.x),
    datasets: resultSet.series().map((s) => {

      const colorScale = chroma.scale(['#10ac84', '#feca57']).domain([ 0, s.series.length - 1 ])
      const colors = s.series.map((r, i) => colorScale(i).hex())

      return {
        label: 'Number of orders',
        data: s.series.map((r) => r.value),
        backgroundColor: colors,
        hoverBackgroundColor: colors,
      }
    }),
  };


  const options = {
    ...commonOptions,
    plugins: {
      legend: {
        position: 'left'
      },
    },
  };

  return <Pie data={data} options={options} />;
};

const Chart = ({ cubejsApi, dateRange }) => {

  return (
    <QueryRenderer
      query={{
        "dimensions": [
          "OrderExport.restaurant"
        ],
        "measures": [
          "OrderExport.count"
        ],
        "timeDimensions": [
          {
            "dimension": "OrderExport.completed_at",
            "dateRange": getCubeDateRange(dateRange)
          }
        ],
        "order": {
          "OrderExport.count": "desc"
        },
        "limit": 10
      }}
      cubejsApi={cubejsApi}
      resetResultSetOnChange={false}
      render={(props) => renderChart({
        ...props,
        chartType: 'pie',
        pivotConfig: {
          "x": [
            "OrderExport.restaurant"
          ],
          "y": [
            "measures"
          ],
          "fillMissingDates": true,
          "joinDateRange": false
        }
      })}
    />
  );
};

export default Chart
