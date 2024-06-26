import React, { useState } from 'react'
import { useTranslation } from 'react-i18next'

import CourierSelect from './CourierSelect'

const ModalContent = ({ onClickClose, onClickSubmit }) => {

  const { t } = useTranslation()
  const [ selected, setSelected ] = useState([])

  return (
    <React.Fragment>
      <div className="modal-header">
        <button type="button" className="close" onClick={ onClickClose } aria-label="Close"><span aria-hidden="true">&times;</span></button>
        <h4 className="modal-title" id="user-modal-label">{ t('ADMIN_DASHBOARD_ADDUSER_TO_PLANNING') }</h4>
      </div>
      <div className="modal-body">
        <div className="row display-flex display-flex__items-centered">
          <div className="col-md-10">
            <form method="post" onSubmit={ (e) => {
            e.preventDefault()
            onClickSubmit(selected)
          } } >
              <div className="form-group" data-action="dispatch">
                <label htmlFor="courier" className="control-label">
                  { t('ADMIN_DASHBOARD_COURIERS') }
                </label>
                <CourierSelect
                  onChange={ selectedCouriers => {
                    setSelected(selectedCouriers)
                  }}
                  isMulti={true}
                  exclude
                />
              </div>
            </form>
          </div>
          <div className="col-md-2">
            <button type="submit" className="btn btn-primary" disabled={ selected.length === 0 } onClick={ () => onClickSubmit(selected) }>{ t('ADMIN_DASHBOARD_ADD') }</button>
          </div>
        </div>
      </div>
      <div className="modal-footer">
        <button type="button" className="btn btn-default" onClick={ onClickClose }>{ t('ADMIN_DASHBOARD_CANCEL') }</button>
      </div>
    </React.Fragment>
  )
}

export default ModalContent
