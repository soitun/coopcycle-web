import React from "react"
import { useDispatch, useSelector } from "react-redux"
import { selectAllVehicles, selectVehicleIdToTaskListIdMap } from "../../../../shared/src/logistics/redux/selectors"
import { Item, Menu } from "react-contexify"
import { useTranslation } from "react-i18next"
import { setTaskListVehicle } from "../../redux/actions"

export default () => {

  const { t } = useTranslation()
  const vehicles = useSelector(selectAllVehicles)
  const vehicleIdToTaskListIdMap = useSelector(selectVehicleIdToTaskListIdMap)
  const dispatch = useDispatch()

  const onVehicleClick = ({ props, data }) =>{
    dispatch(setTaskListVehicle(props.username, data.vehicleId))
  }

  return (
    <Menu id="vehicle-selectmenu">
      <Item key={-1} onClick={onVehicleClick} data={{vehicleId: null}}>{ t('CLEAR') }</Item>
      {
        vehicles.map((vehicle, index) => {
          return (
            <Item
              onClick={onVehicleClick}
              data={{vehicleId: vehicle['@id']}}
              disabled={vehicleIdToTaskListIdMap.has(vehicle['@id'])}
              key={index} >
                {vehicle.name}
            </Item>)
        })
      }
    </Menu>
  )

}