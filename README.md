# Yesvo
Novo batch export command line tool

## Requirements
PHP with cURL extension

## Usage
With PHP installed, navigate to the file's directory and execute via `php main.php` with the following parameters:

* `-u username_here`
* `-p password_here`
* `-i report_id_here`
* `-d params,comma,separated` (can include multiple times for multiple reports)
* `-e environment_here` (optional - production or development)

## Example Usage
`php main.php -u john.doe -p MyP@ssword -i 1234`

Exports report # 1234 to `data/1234.csv`

---

`php main.php -u john.doe -p MyP@assword -i 4321 -d 1/01/2013,1/31/2013`

Exports report # 4321 using params `1/01/2013` and `1/31/2013` to `data/4321_01-01-2013_01-31-2013.csv`

---

`php main.php -u john.doe -p MyP@ssword -i 4321 -d 1/01/2013,1/31/2013 -d 2/01/2013,2/28/2013`

Exports report # 4321 using params `1/01/2013` and `1/31/2013` to `data/4321_01-01-2013_01-31-2013.csv`, exports report # 4321 using params `1/01/2013` and `1/28/2013` to `data/4321_02-01-2013_02-28-2013.csv`, and then merges them together to `data/Merge_xxxxxx.csv`.