describe('Checkout; non business context; logged in customer; fulfilment method: delivery: happy path', () => {
  beforeEach(() => {
    cy.loadFixtures('../cypress/fixtures/checkout.yml')

    cy.intercept('POST', '/fr/restaurant/*/cart').as('postRestaurantCart')
    cy.intercept('POST', '/fr/restaurant/*/cart/product/*').as('postProduct')
  })

  it('order something at restaurant', () => {
    cy.login('bob', '12345678')

    cy.visit('/fr/')

    cy.clickRestaurant(
      'Crazy Hamburger',
      /\/fr\/restaurant\/[0-9]+-crazy-hamburger/,
    )

    cy.wait('@postRestaurantCart')

    cy.addProduct('Cheeseburger', '#CHEESEBURGER_crazy_hamburger-options', 1, [
      'HAMBURGER_ACCOMPANIMENT_FRENCH_FRIES_crazy_hamburger',
      'HAMBURGER_DRINK_COLA_crazy_hamburger',
    ])

    cy.wait('@postProduct', { timeout: 5000 })

    cy.get('.cart__items')
      .invoke('text')
      .should('match', /Cheeseburger/)

    cy.searchAddressUsingAddressModal(
      '.ReactModal__Content--enter-address',
      '91 rue de rivoli paris',
      /^91,? Rue de Rivoli,? 75001,? Paris,? France/i,
    )

    cy.wait('@postRestaurantCart')

    cy.get(
      '#restaurant__fulfilment-details__container [data-testid="cart.shippingAddress"]',
    )
      .invoke('text')
      .should('match', /^91,? Rue de Rivoli,? 75001,? Paris,? France/i)

    cy.addProduct('Cheese Cake', '#CHEESECAKE_crazy_hamburger-options')

    cy.wait('@postProduct', { timeout: 5000 })

    cy.get('.cart__items', { timeout: 10000 })
      .invoke('text')
      .should('match', /Cheese Cake/)

    cy.get('form[name="cart"]').submit()

    cy.urlmatch(/\/order\/$/)

    cy.get('input[name="checkout_address[customer][fullName]"]').type(
      'John Doe',
    )

    cy.contains('Commander').click()

    cy.urlmatch(/\/order\/payment$/)

    cy.get('form[name="checkout_payment"] input[type="text"]').type('John Doe')
    cy.enterCreditCard()

    cy.get('form[name="checkout_payment"]').submit()

    cy.urlmatch(/\/order\/confirm\/[a-zA-Z0-9]+/)

    cy.get('#order-timeline').contains('Commande en attente de validation')
  })
})
