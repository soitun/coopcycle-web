import axios from 'axios'
import React, { useEffect, useState } from 'react'
import { Button, Input } from 'antd'
import { useFormikContext } from 'formik'

const baseURL = location.protocol + '//' + location.host

export default ({ storeId, index }) => {
  const { setFieldValue } = useFormikContext()

  const [packagesType, setPackagesType] = useState([])
  const [packagesPicked, setPackagesPicked] = useState([])

  console.log('PackagesPIcked', packagesPicked)

  console.group(index)

  useEffect(() => {
    const getPackagesType = async () => {
      const jwtResp = await $.getJSON(window.Routing.generate('profile_jwt'))
      const jwt = jwtResp.jwt
      const url = `${baseURL}/api/stores/${storeId}/packages`

      const response = await axios.get(url, {
        headers: {
          Authorization: `Bearer ${jwt}`,
        },
      })
      const packages = await response.data['hydra:member']
      console.group(packages)
      setPackagesType(packages)
    }

    if (storeId) {
      getPackagesType()
    }
  }, [storeId])

  useEffect(() => {
    setFieldValue(`tasks[${index}].packages`, packagesPicked)
  }, [packagesPicked])

  const handlePlusButton = item => {
    setPackagesPicked([...packagesPicked, item])
  }

  const handleMinusButton = item => {
    const index = packagesPicked.findIndex(p => p === item)
    if (index !== -1) {
      const newPackagesPicked = [...packagesPicked]
      newPackagesPicked.splice(index, 1)
      setPackagesPicked(newPackagesPicked)
    }
  }

  const calculateValue = item => {
    const sameTypePackages = packagesPicked.filter(p => p === item)
    return sameTypePackages.length
  }

  return (
    <>
      <div>Packages</div>
      {packagesType.map(item => (
        <div key={item['@id']} className="row mb-2">
          <div className="col-xs-3">
            <div className="row">
              <div className="col-xs-3 pr-1">
                <Button
                  color="default"
                  variant="filled"
                  onClick={() => handleMinusButton(item)}>
                  -
                </Button>
              </div>
              <div className="col-xs-6 px-1 text-center">
                <Input value={calculateValue(item)} />
              </div>
              <div className="col-xs-3 pl-1">
                <Button
                  color="default"
                  variant="filled"
                  onClick={() => {
                    handlePlusButton(item)
                    calculateValue(item)
                  }}>
                  +
                </Button>
              </div>
            </div>
          </div>
          <div className="col-xs-9">
            <Input readOnly value={item.name} />
          </div>
        </div>
      ))}
    </>
  )
}
