// https://cube.dev/docs/multitenancy-setup#multiple-db-instances-with-same-schema
// https://cube.dev/docs/product/configuration/advanced/multitenancy#multiple-db-instances-with-same-data-model

module.exports = {
  extendContext: (req) => {
    return {
      securityContext: {
        ...req.securityContext,
      }
    }
  },
  contextToAppId: ({ securityContext }) =>
    `CUBEJS_APP_${securityContext?.database || 'coopcycle'}`,
  contextToOrchestratorId: ({ securityContext }) =>
    `CUBEJS_APP_${securityContext?.database || 'coopcycle'}`,
  driverFactory: ({ securityContext, dataSource }) => {

    if (dataSource === 'clickhouse') {
      return {
        type: 'clickhouse',
        host: 'clickhouse',
        port: '8123',
        database: 'coopcycle',
        username: 'coopcycle',
        password: 'coopcycle',
        ssl: false,
      }
    }

    return {
      type: 'postgres',
      database: `${securityContext?.database || 'coopcycle'}`,
    }
  },


  // https://cube.dev/docs/config#options-reference-scheduled-refresh-contexts
  scheduledRefreshContexts: () => [
    {
      securityContext: {
        database: 'coopcycle',
        base_url: 'http://nginx',
        instance: 'default'
      },
    },
  ],
};
