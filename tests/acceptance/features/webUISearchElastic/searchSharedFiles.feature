@webUI @insulated @disablePreviews
Feature: Search

As a user
I would like to be able to search for the content of files
So that I can find needed files quickly

  Background:
    Given these users have been created:
    |username|password|displayname|email       |
    |user1   |1234    |User One   |u1@oc.com.np|
    And files of user "user1" have been indexed
    And the user has browsed to the login page
    And the user has logged in with username "user1" and password "1234" using the webUI

  @skip @issue-36
  Scenario: user searches for files shared to him as a single user
    Given user "user2" has been created
    And user "user2" has created a folder "/just-a-folder"
    And user "user2" has uploaded file with content "files content" to "/upload.txt"
    And user "user2" has uploaded file with content "file with content in subfolder" to "/just-a-folder/upload.txt"
    And all files have been indexed
    And user "user2" has shared file "upload.txt" with user "user1"
    And user "user2" has shared folder "just-a-folder" with user "user1"
    And all files have been indexed
    And the user has reloaded the current page of the webUI
    When the user searches for "content" using the webUI
    Then the file "upload.txt" with the path "/" should be listed in the search results in other folders section on the webUI
    And the file "upload.txt" with the path "/just-a-folder" should be listed in the search results in other folders section on the webUI

  Scenario: user searches for files shared to him as a single user (files have been indexed only after sharing)
    Given user "user2" has been created
    And user "user2" has created a folder "/just-a-folder"
    And user "user2" has uploaded file with content "files content" to "/upload.txt"
    And user "user2" has uploaded file with content "file with content in subfolder" to "/just-a-folder/upload.txt"
    And user "user2" has shared file "upload.txt" with user "user1"
    And user "user2" has shared folder "just-a-folder" with user "user1"
    And all files have been indexed
    And the user has reloaded the current page of the webUI
    When the user searches for "content" using the webUI
    Then the file "upload.txt" with the path "/" should be listed in the search results in other folders section on the webUI
    And the file "upload.txt" with the path "/just-a-folder" should be listed in the search results in other folders section on the webUI

  @skip @issue-36
  Scenario: user searches for files shared to him as a member of a group
    Given user "user2" has been created
    And group "grp1" has been created
    And user "user1" has been added to group "grp1"
    And user "user2" has created a folder "/just-a-folder"
    And user "user2" has uploaded file with content "files content" to "/upload.txt"
    And user "user2" has uploaded file with content "file with content in subfolder" to "/just-a-folder/upload.txt"
    And all files have been indexed
    And user "user2" has shared file "upload.txt" with user "user1"
    And user "user2" has shared folder "just-a-folder" with user "user1"
    And all files have been indexed
    And the user has reloaded the current page of the webUI
    When the user searches for "content" using the webUI
    Then the file "upload.txt" with the path "/" should be listed in the search results in other folders section on the webUI
    And the file "upload.txt" with the path "/just-a-folder" should be listed in the search results in other folders section on the webUI

  Scenario: user searches for files shared to him as a member of a group (files have been indexed only after sharing)
    Given user "user2" has been created
    And group "grp1" has been created
    And user "user1" has been added to group "grp1"
    And user "user2" has created a folder "/just-a-folder"
    And user "user2" has uploaded file with content "files content" to "/upload.txt"
    And user "user2" has uploaded file with content "file with content in subfolder" to "/just-a-folder/upload.txt"
    And user "user2" has shared file "upload.txt" with user "user1"
    And user "user2" has shared folder "just-a-folder" with user "user1"
    And all files have been indexed
    And the user has reloaded the current page of the webUI
    When the user searches for "content" using the webUI
    Then the file "upload.txt" with the path "/" should be listed in the search results in other folders section on the webUI
    And the file "upload.txt" with the path "/just-a-folder" should be listed in the search results in other folders section on the webUI

  @skip @issue-36
  Scenario: unshared files should not be searched
    Given user "user2" has been created
    And user "user2" has created a folder "/just-a-folder"
    And user "user2" has uploaded file with content "files content" to "/upload.txt"
    And user "user2" has uploaded file with content "files content" to "/upload-keep.txt"
    And user "user2" has uploaded file with content "file with content in subfolder" to "/just-a-folder/upload.txt"
    And all files have been indexed
    And user "user2" has shared file "upload.txt" with user "user1"
    And user "user2" has shared file "upload-keep.txt" with user "user1"
    And user "user2" has shared folder "just-a-folder" with user "user1"
    And all files have been indexed
    And the user has reloaded the current page of the webUI
    When the user unshares the file "upload.txt" using the webUI
    And the user unshares the folder "just-a-folder" using the webUI
    And the administrator indexes all files
    And the user searches for "content" using the webUI
    Then the file "upload-keep.txt" with the path "/" should be listed in the search results in other folders section on the webUI
    And the file "upload.txt" with the path "/" should not be listed in the search results in other folders section on the webUI
    But the file "upload.txt" with the path "/just-a-folder" should not be listed in the search results in other folders section on the webUI

  Scenario: unshared files should not be searched (files have been indexed only after sharing)
    Given user "user2" has been created
    And user "user2" has created a folder "/just-a-folder"
    And user "user2" has uploaded file with content "files content" to "/upload.txt"
    And user "user2" has uploaded file with content "files content" to "/upload-keep.txt"
    And user "user2" has uploaded file with content "file with content in subfolder" to "/just-a-folder/upload.txt"
    And user "user2" has shared file "upload.txt" with user "user1"
    And user "user2" has shared file "upload-keep.txt" with user "user1"
    And user "user2" has shared folder "just-a-folder" with user "user1"
    And all files have been indexed
    And the user has reloaded the current page of the webUI
    When the user unshares the file "upload.txt" using the webUI
    And the user unshares the folder "just-a-folder" using the webUI
    And the administrator indexes all files
    And the user searches for "content" using the webUI
    Then the file "upload-keep.txt" with the path "/" should be listed in the search results in other folders section on the webUI
    And the file "upload.txt" with the path "/" should not be listed in the search results in other folders section on the webUI
    But the file "upload.txt" with the path "/just-a-folder" should not be listed in the search results in other folders section on the webUI

  Scenario: unshared files should not be searched (files have been indexed only after unsharing)
    Given user "user2" has been created
    And user "user2" has created a folder "/just-a-folder"
    And user "user2" has uploaded file with content "files content" to "/upload.txt"
    And user "user2" has uploaded file with content "files content" to "/upload-keep.txt"
    And user "user2" has uploaded file with content "file with content in subfolder" to "/just-a-folder/upload.txt"
    And user "user2" has shared file "upload.txt" with user "user1"
    And user "user2" has shared file "upload-keep.txt" with user "user1"
    And user "user2" has shared folder "just-a-folder" with user "user1"
    And the user has reloaded the current page of the webUI
    When the user unshares the file "upload.txt" using the webUI
    And the user unshares the folder "just-a-folder" using the webUI
    And the administrator indexes all files
    And the user searches for "content" using the webUI
    Then the file "upload-keep.txt" with the path "/" should be listed in the search results in other folders section on the webUI
    And the file "upload.txt" with the path "/" should not be listed in the search results in other folders section on the webUI
    But the file "upload.txt" with the path "/just-a-folder" should not be listed in the search results in other folders section on the webUI

  @skip @issue-36
  Scenario: user searches for files re-shared to him
    Given user "user2" has been created
    And user "user3" has been created
    And user "user3" has created a folder "/just-a-folder"
    And user "user3" has uploaded file with content "files content" to "/upload.txt"
    And user "user3" has uploaded file with content "file with content in subfolder" to "/just-a-folder/upload.txt"
    And all files have been indexed
    And user "user3" has shared file "upload.txt" with user "user2"
    And user "user3" has shared folder "just-a-folder" with user "user2"
    And user "user2" has shared file "upload.txt" with user "user1"
    And user "user2" has shared folder "just-a-folder" with user "user1"
    And all files have been indexed
    And the user has reloaded the current page of the webUI
    When the user searches for "content" using the webUI
    Then the file "upload.txt" with the path "/" should be listed in the search results in other folders section on the webUI
    And the file "upload.txt" with the path "/just-a-folder" should be listed in the search results in other folders section on the webUI

  Scenario: user searches for files re-shared to him (files have been indexed only after second sharing)
    Given user "user2" has been created
    And user "user3" has been created
    And user "user3" has created a folder "/just-a-folder"
    And user "user3" has uploaded file with content "files content" to "/upload.txt"
    And user "user3" has uploaded file with content "file with content in subfolder" to "/just-a-folder/upload.txt"
    And user "user3" has shared file "upload.txt" with user "user2"
    And user "user3" has shared folder "just-a-folder" with user "user2"
    And user "user2" has shared file "upload.txt" with user "user1"
    And user "user2" has shared folder "just-a-folder" with user "user1"
    And all files have been indexed
    And the user has reloaded the current page of the webUI
    When the user searches for "content" using the webUI
    Then the file "upload.txt" with the path "/" should be listed in the search results in other folders section on the webUI
    And the file "upload.txt" with the path "/just-a-folder" should be listed in the search results in other folders section on the webUI