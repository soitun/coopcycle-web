export type PricingRuleType = {
  '@id': string
  target: string
  expression: string
  expressionAst?: object
  price: string
  priceAst?: object
  position: number
  name: string | null
}
