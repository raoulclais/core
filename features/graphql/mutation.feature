Feature: GraphQL Mutation support

  @createSchema
  Scenario: Execute an empty GraphQL query
    When I send a "GET" request to "/graphql"
    Then the response status code should be 200
    And the response should be in JSON
    And the header "Content-Type" should be equal to "application/json"

  Scenario: Retrieve metadata about GraphQL Schema
    When I send a "GET" request to '/graphql?query={__type(name:"Dummy") {description, fields {name}}}'
    Then the response status code should be 200
    And the response should be in JSON
    And the header "Content-Type" should be equal to "application/json"

  @dropSchema
  Scenario: Retrieve an item through a GraphQL query
    Given there is "30" dummy objects
    When I send a "GET" request to '/graphql?query={item_get_Dummy(id:3) {name}}'
    Then the response status code should be 200
    And the response should be in JSON
    And the header "Content-Type" should be equal to "application/json"
    And the response should contain "Dummy #3"