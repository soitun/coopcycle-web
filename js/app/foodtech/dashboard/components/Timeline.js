import React from 'react'
import { withTranslation } from 'react-i18next'
import { Timeline } from 'antd'
import moment from 'moment'

import { asText } from '../../../components/ShippingTimeRange'

export default withTranslation()(({ order, t }) => {
  const timelineItems = [
    {
      key: 'preparation',
      dot: <i className="fa fa-cutlery"></i>,
      children: (
        <>
          <div>
            <strong>
              {t('RESTAURANT_DASHBOARD_PREPARATION_AT', {
                time: moment(order.preparationExpectedAt).format('LT'),
              })}
            </strong>
          </div>
          <ul className="list-unstyled">
            {order.reusablePackagingEnabled && (
              <li>
                <span className="text-warning">
                  {t('ADMIN_DASHBOARD_ORDERS_PACKAGING_ALERT')}
                </span>
              </li>
            )}
            {order.preparationTime && (
              <li>
                <span className="text-muted">{order.preparationTime}</span>
              </li>
            )}
          </ul>
        </>
      ),
    },
    {
      key: 'pickup',
      dot: <i className="fa fa-cube"></i>,
      children: (
        <>
          <div>
            <strong>
              {t('RESTAURANT_DASHBOARD_PICKUP_AT', {
                time: moment(order.pickupExpectedAt).format('LT'),
              })}
            </strong>
          </div>
          <ul className="list-unstyled">
            <li>
              <span>{order.vendor.address.streetAddress}</span>
            </li>
            {order.takeaway && (
              <li>
                <span className="text-warning">
                  {t('ADMIN_DASHBOARD_ORDERS_TAKEAWAY_ALERT')}
                </span>
              </li>
            )}
            {!order.takeaway && order.shippingTime && (
              <li>
                <span className="text-muted">{order.shippingTime}</span>
              </li>
            )}
          </ul>
        </>
      ),
    },
  ]

  // Add dropoff item conditionally
  if (!order.takeaway) {
    timelineItems.push({
      key: 'dropoff',
      dot: <i className="fa fa-arrow-down"></i>,
      children: (
        <>
          <div>
            <strong>
              {t('RESTAURANT_DASHBOARD_DROPOFF_AT', {
                time: asText(order.shippingTimeRange, true),
              })}
            </strong>
          </div>
          <ul className="list-unstyled">
            <li>{order.shippingAddress.streetAddress}</li>
          </ul>
          {order.shippingAddress.description && (
            <div className="speech-bubble">
              <i className="fa fa-quote-left"></i>{' '}
              {order.shippingAddress.description}
            </div>
          )}
        </>
      ),
    })
  }

  return <Timeline items={timelineItems} />
})
