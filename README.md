# Pouët.net

The main [Pouët.net](https://www.pouet.net) website.

## Running it locally

The local stack (Docker images matching production, configs and sample
data) lives in [pouet-infra](https://github.com/pouetnet/pouet-infra),
which is only open to the team for now. If you have access:

```sh
git clone git@github.com:pouetnet/pouet-infra.git
cd pouet-infra
just setup
```

This clones this repo and the other site repos under `sites/`, starts
everything and loads sample data. www is then on
<http://www.pouet.localhost>, and <http://www.pouet.localhost/login.php>
logs you in as user 1, an admin. Its `README.md` and `TESTING.md` have the
details.

## Contributing

Open a pull request against `master`. PRs are squash-merged once CI
passes, and a merged PR is live on www.pouet.net within a minute.

If your change alters the database schema, update `pouet.sql` in the same
PR.

PHP follows [PER Coding Style 3.0](https://www.php-fig.org/per/coding-style/).
CI flags style issues without blocking the merge; `php-cs-fixer fix`
(3.95.27) fixes them.

## Team

### Former maintainers

- [analogue](http://pouet.net/user.php?who=1) (2000-2004)
- [psenough](http://pouet.net/user.php?who=177) (2004-2007)
- [gargaj](http://pouet.net/user.php?who=1007) (2007-2024)

### Contributors

- [mog](http://pouet.net/user.php?who=26747)
- [Ziphoid](http://pouet.net/user.php?who=26956)
- [kusma](http://pouet.net/user.php?who=1383)
- [D.Fox](http://pouet.net/user.php?who=374)
- [gasman](http://pouet.net/user.php?who=2260)
- [knos](http://pouet.net/user.php?who=36)
- [p01](http://pouet.net/user.php?who=59)
- [redhound](http://pouet.net/user.php?who=784)
- [stil](http://pouet.net/user.php?who=351)
- [rez](http://pouet.net/user.php?who=10)
- [melwyn](http://pouet.net/user.php?who=38)
